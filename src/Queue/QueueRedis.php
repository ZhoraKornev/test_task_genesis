<?php

namespace App\Queue;
use App\Exceptions\MessageToLongException;
use App\Exceptions\QueueNotFoundException;
use App\Exceptions\RedisNotConnectedException;
use Islambey\RSMQ\Exception;
use JetBrains\PhpStorm\ArrayShape;

class QueueRedis
{
    protected RedisClient $redisClient;
    private string $ns;
    private bool $realtime;

    /**
     * @var mixed|\Redis
     */
    private mixed $receiveMessageSha1;
    /**
     * @var mixed|\Redis
     */
    private mixed $popMessageSha1;
    /**
     * @var mixed|\Redis
     */
    private mixed $changeMessageVisibilitySha1;

    private QueueValidator $validator;

    private ValueFormatHelper $valueHelper;

    public function __construct(string $ns = EnvDefaultsQueue::QUEUE_NAME_SPACE_DEFAULT, bool $realtime = false)
    {
        $this->redisClient = new RedisClient();
        $this->ns = "$ns:";
        $this->realtime = $realtime;
        $this->validator = new QueueValidator();
        $this->valueHelper = new ValueFormatHelper();
    }

    /**
     * @return void
     * @throws RedisNotConnectedException
     * @throws \App\Exceptions\EnvHelperInitializationException
     */
    public function init(): void
    {
        $this->redisClient->initConnection();
        $this->initScripts();
    }


    /**
     * @throws Exception
     * @throws \App\Exceptions\QueueValidationException
     * @throws \Exception
     */
    public function createQueue(string $name, int $vt = 30, int $delay = 0, int $maxSize = 65536): bool
    {
        $this->validator->validate([
            'queue' => $name,
            'vt' => $vt,
            'delay' => $delay,
            'maxsize' => $maxSize,
        ]);

        $key = "{$this->ns}$name:Q";

        $resp = $this->redisClient->getRedis()->time();
        $transaction = $this->redisClient->getRedis()->multi();
        $transaction->hSetNx($key, 'vt', (string)$vt);
        $transaction->hSetNx($key, 'delay', (string)$delay);
        $transaction->hSetNx($key, 'maxsize', (string)$maxSize);
        $transaction->hSetNx($key, 'created', $resp[0]);
        $transaction->hSetNx($key, 'modified', $resp[0]);
        $resp = $transaction->exec();
        if (!$resp[0]) {
            throw new \Exception('Queue already exists.');
        }

        return (bool)$this->redisClient->getRedis()->sAdd("{$this->ns}QUEUES", $name);
    }

    /**
     * @return array<string>
     */
    public function listQueues(): array
    {
        return $this->redisClient->listQueues("{$this->ns}QUEUES");
    }

    /**
     * @param string $name
     * @return void
     * @throws Exception
     * @throws \App\Exceptions\QueueValidationException
     */
    public function deleteQueue(string $name): void
    {
        $this->validator->validate([
            'queue' => $name,
        ]);

        $key = "{$this->ns}$name";
        $transaction = $this->redisClient->getRedis()->multi();
        $transaction->del("$key:Q", $key);
        $transaction->srem("{$this->ns}QUEUES", $name);
        $resp = $transaction->exec();

        if (!$resp[0]) {
            throw new Exception('Queue not found.');
        }
    }

    /**
     * @param string $queue
     * @return QueueAttributesModel
     * @throws QueueNotFoundException
     * @throws \App\Exceptions\QueueValidationException
     */
    public function getQueueAttributes(string $queue): QueueAttributesModel
    {
        $this->validator->validate(
            [
                'queue' => $queue,
            ]
        );

        $key  = "{$this->ns}$queue";
        $resp = $this->redisClient->getRedis()->time();

        /**
         * @psalm-suppress UndefinedMagicMethod
         */
        $transaction = $this->redisClient->getRedis()->multi();
        $transaction->hMGet("$key:Q", ['vt', 'delay', 'maxsize', 'totalrecv', 'totalsent', 'created', 'modified']);
        $transaction->zCard($key);
        $transaction->zCount($key, $resp[0] . '0000', "+inf");
        $resp = $transaction->exec();

        if ($resp[0]['vt'] === false) {
            throw new QueueNotFoundException();
        }

        return new QueueAttributesModel(
            (int)$resp[0]['vt'],
            (int)$resp[0]['delay'],
            (int)$resp[0]['maxsize'],
            (int)$resp[0]['totalrecv'],
            (int)$resp[0]['totalsent'],
            (int)$resp[0]['created'],
            (int)$resp[0]['modified'],
            $resp[1],
            $resp[2]
        );
    }

    /**
     * @param string $queue
     * @param int|null $vt
     * @param int|null $delay
     * @param int|null $maxSize
     * @return QueueAttributesModel
     * @throws Exception
     * @throws QueueNotFoundException
     * @throws \App\Exceptions\QueueValidationException
     */
    public function setQueueAttributes(string $queue, int $vt = null, int $delay = null, int $maxSize = null): QueueAttributesModel
    {
        $this->validator->validate([
            'vt' => $vt,
            'delay' => $delay,
            'maxsize' => $maxSize,
        ]);
        $this->getQueue($queue);

        $time = $this->redisClient->getRedis()->time();
        $transaction = $this->redisClient->getRedis()->multi();

        $transaction->hSet("{$this->ns}$queue:Q", 'modified', $time[0]);
        if ($vt !== null) {
            $transaction->hSet("{$this->ns}$queue:Q", 'vt', (string)$vt);
        }

        if ($delay !== null) {
            $transaction->hSet("{$this->ns}$queue:Q", 'delay', (string)$delay);
        }

        if ($maxSize !== null) {
            $transaction->hSet("{$this->ns}$queue:Q", 'maxsize', (string)$maxSize);
        }

        $transaction->exec();

        return $this->getQueueAttributes($queue);
    }

    /**
     * @param string $queue
     * @param string $message
     * @param int|null $delay
     * @return string
     * @throws Exception
     * @throws MessageToLongException
     * @throws \App\Exceptions\QueueValidationException
     */
    public function sendMessage(string $queue, string $message, int $delay = null): string
    {
        $this->validator->validate(
            [
                'queue' => $queue,
            ]
        );

        $q = $this->getQueue($queue, true);
        if ($delay === null) {
            $delay = $q['delay'];
        }

        if ($q['maxsize'] !== -1 && mb_strlen($message) > $q['maxsize']) {
            throw new MessageToLongException();
        }

        $key = "{$this->ns}$queue";

        $transaction = $this->redisClient->getRedis()->multi();
        $transaction->zadd($key, $q['ts'] + $delay * 1000, $q['uid']);
        $transaction->hSet("$key:Q", $q['uid'], $message);
        $transaction->hIncrBy("$key:Q", 'totalsent', 1);

        if ($this->realtime) {
            $transaction->zCard($key);
        }

        $resp = $transaction->exec() ?? [];

        if ($this->realtime) {
            $this->redisClient->getRedis()->publish("{$this->ns}rt:$$queue", $resp[3]);
        }

        return $q['uid'];
    }

    public function receiveMessage(string $queue, array $options = []): ?Message
    {
        $this->validator->validate([
            'queue' => $queue,
        ]);

        $q = $this->getQueue($queue);
        $vt = $options['vt'] ?? $q['vt'];

        $args = [
            "{$this->ns}$queue",
            $q['ts'],
            $q['ts'] + $vt * 1000
        ];
        $resp = $this->redisClient->getRedis()->evalSha($this->receiveMessageSha1, $args, 3);
        if (empty($resp)) {
            return null;
        }

        return new Message(
            (string)$resp[0],
            (string)$resp[1],
            (int)$resp[2],
            (int)$resp[3],
            (float)base_convert(substr($resp[0], 0, EnvDefaultsQueue::BASE_CONVERT_FROM),
                EnvDefaultsQueue::BASE_CONVERT_TO,
                EnvDefaultsQueue::BASE_CONVERT_FROM) / 1000
        );
    }

    public function popMessage(string $queue): ?Message
    {
        $this->validator->validate([
            'queue' => $queue,
        ]);

        $q = $this->getQueue($queue);

        $args = [
            "{$this->ns}$queue",
            $q['ts'],
        ];
        $resp = $this->redisClient->getRedis()->evalSha($this->popMessageSha1, $args, 2);
        if (empty($resp)) {
            return null;
        }

        return new Message(
            (string)$resp[0],
            (string)$resp[1],
            (int)$resp[2],
            (int)$resp[3],
            (float)base_convert(substr($resp[0], 0, EnvDefaultsQueue::BASE_CONVERT_FROM),
                EnvDefaultsQueue::BASE_CONVERT_TO,
                EnvDefaultsQueue::BASE_CONVERT_FROM) / 1000
        );
    }

    public function deleteMessage(string $queue, string $id): bool
    {
        $this->validator->validate([
            'queue' => $queue,
            'id' => $id,
        ]);

        $key = "{$this->ns}$queue";
        $transaction = $this->redisClient->getRedis()->multi();
        $transaction->zRem($key, $id);
        $transaction->hDel("$key:Q", $id, "$id:rc", "$id:fr");
        $resp = $transaction->exec();

        return $resp[0] === 1 && $resp[1] > 0;
    }

    /**
     * @param string $name
     * @param bool $uid
     * @return mixed[]
     * @throws Exception
     * @throws \App\Exceptions\QueueValidationException
     */
    #[ArrayShape(['vt' => "int", 'delay' => "int", 'maxsize' => "int", 'ts' => "int", 'uid' => "string"])]
    private function getQueue(string $name, bool $uid = false): array
    {
        $this->validator->validate([
            'queue' => $name,
        ]);

        $transaction = $this->redisClient->getRedis()->multi();
        $transaction->hmget("{$this->ns}$name:Q", ['vt', 'delay', 'maxsize']);
        $transaction->time();
        $resp = $transaction->exec();

        if ($resp[0]['vt'] === false) {
            throw new Exception('Queue not found.');
        }

        $ms = $this->valueHelper->formatZeroPad((int)$resp[1][1], 6);

        $queue = [
            'vt' => (int)$resp[0]['vt'],
            'delay' => (int)$resp[0]['delay'],
            'maxsize' => (int)$resp[0]['maxsize'],
            'ts' => (int)($resp[1][0] . substr($ms, 0, 3)),
        ];

        if ($uid) {
            $uid = $this->valueHelper->makeID(EnvDefaultsQueue::STANDART_ID_LENGTH);
            $queue['uid'] = base_convert(($resp[1][0] . $ms), EnvDefaultsQueue::BASE_CONVERT_FROM, EnvDefaultsQueue::BASE_CONVERT_TO) . $uid;
        }

        return $queue;
    }

    private function initScripts(): void
    {
        $receiveMessageScript = 'local msg = redis.call("ZRANGEBYSCORE", KEYS[1], "-inf", KEYS[2], "LIMIT", "0", "1")
			if #msg == 0 then
				return {}
			end
			redis.call("ZADD", KEYS[1], KEYS[3], msg[1])
			redis.call("HINCRBY", KEYS[1] .. ":Q", "totalrecv", 1)
			local mbody = redis.call("HGET", KEYS[1] .. ":Q", msg[1])
			local rc = redis.call("HINCRBY", KEYS[1] .. ":Q", msg[1] .. ":rc", 1)
			local o = {msg[1], mbody, rc}
			if rc==1 then
				redis.call("HSET", KEYS[1] .. ":Q", msg[1] .. ":fr", KEYS[2])
				table.insert(o, KEYS[2])
			else
				local fr = redis.call("HGET", KEYS[1] .. ":Q", msg[1] .. ":fr")
				table.insert(o, fr)
			end
			return o';

        $popMessageScript = 'local msg = redis.call("ZRANGEBYSCORE", KEYS[1], "-inf", KEYS[2], "LIMIT", "0", "1")
			if #msg == 0 then
				return {}
			end
			redis.call("HINCRBY", KEYS[1] .. ":Q", "totalrecv", 1)
			local mbody = redis.call("HGET", KEYS[1] .. ":Q", msg[1])
			local rc = redis.call("HINCRBY", KEYS[1] .. ":Q", msg[1] .. ":rc", 1)
			local o = {msg[1], mbody, rc}
			if rc==1 then
				table.insert(o, KEYS[2])
			else
				local fr = redis.call("HGET", KEYS[1] .. ":Q", msg[1] .. ":fr")
				table.insert(o, fr)
			end
			redis.call("ZREM", KEYS[1], msg[1])
			redis.call("HDEL", KEYS[1] .. ":Q", msg[1], msg[1] .. ":rc", msg[1] .. ":fr")
			return o';

        $changeMessageVisibilityScript = 'local msg = redis.call("ZSCORE", KEYS[1], KEYS[2])
			if not msg then
				return 0
			end
			redis.call("ZADD", KEYS[1], KEYS[3], KEYS[2])
			return 1';

        $this->receiveMessageSha1 = $this->redisClient->getRedis()->script('load', $receiveMessageScript);
        $this->popMessageSha1 = $this->redisClient->getRedis()->script('load', $popMessageScript);
        $this->changeMessageVisibilitySha1 = $this->redisClient->getRedis()->script('load', $changeMessageVisibilityScript);
    }
}
