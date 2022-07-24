<?php

namespace App\Queue;

use App\Exceptions\QueueNotFoundException;
use App\Exceptions\RedisNotConnectedException;
use Islambey\RSMQ\Exception;

abstract class QueueRedis
{
    private const QUEUE_NAME_DEFAULT = 'default';
    private const QUEUE_NAME_SPACE_DEFAULT = 'default_ns';
    private const MAX_DELAY        = 9999999;
    private const MIN_MESSAGE_SIZE = 1024;
    private const MAX_PAYLOAD_SIZE = 65536;

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

    public function __construct(string $ns = QueueRedis::QUEUE_NAME_SPACE_DEFAULT, bool $realtime = false)
    {
        $this->redisClient = new RedisClient();
        $this->ns = "$ns:";
        $this->realtime = $realtime;


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


    public function createQueue(string $name, int $vt = 30, int $delay = 0, int $maxSize = 65536): bool
    {
        $this->validate([
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
            throw new Exception('Queue already exists.');
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

    public function deleteQueue(string $name): void
    {
        $this->validate([
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
     * @throws Exception
     * @throws QueueNotFoundException
     */
    public function getQueueAttributes(string $queue): QueueAttributesModel
    {
        $this->validate(
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
     */
    public function setQueueAttributes(string $queue, int $vt = null, int $delay = null, int $maxSize = null): QueueAttributesModel
    {
        $this->validate([
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


    private function getQueue(string $name, bool $uid = false): array
    {
        $this->validate([
            'queue' => $name,
        ]);

        $transaction = $this->redisClient->getRedis()->multi();
        $transaction->hmget("{$this->ns}$name:Q", ['vt', 'delay', 'maxsize']);
        $transaction->time();
        $resp = $transaction->exec();

        if ($resp[0]['vt'] === false) {
            throw new Exception('Queue not found.');
        }

        $ms = $this->formatZeroPad((int)$resp[1][1], 6);

        $queue = [
            'vt' => (int)$resp[0]['vt'],
            'delay' => (int)$resp[0]['delay'],
            'maxsize' => (int)$resp[0]['maxsize'],
            'ts' => (int)($resp[1][0] . substr($ms, 0, 3)),
        ];

        if ($uid) {
            $uid = $this->makeID(22);
            $queue['uid'] = base_convert(($resp[1][0] . $ms), 10, 36) . $uid;
        }

        return $queue;
    }



    public function validate(array $params): void
    {
        if (isset($params['queue']) && !preg_match('/^([a-zA-Z0-9_-]){1,160}$/', $params['queue'])) {
            throw new Exception('Invalid queue name');
        }

        if (isset($params['id']) && !preg_match('/^([a-zA-Z0-9:]){32}$/', $params['id'])) {
            throw new Exception('Invalid message id');
        }

        if (isset($params['vt']) && ($params['vt'] < 0 || $params['vt'] > self::MAX_DELAY)) {
            throw new Exception('Visibility time must be between 0 and ' . self::MAX_DELAY);
        }

        if (isset($params['delay']) && ($params['delay'] < 0 || $params['delay'] > self::MAX_DELAY)) {
            throw new Exception('Delay must be between 0 and ' . self::MAX_DELAY);
        }

        if (isset($params['maxsize']) &&
            ($params['maxsize'] < self::MIN_MESSAGE_SIZE || $params['maxsize'] > self::MAX_PAYLOAD_SIZE)) {
            $message = "Maximum message size must be between %d and %d";
            throw new Exception(sprintf($message, self::MIN_MESSAGE_SIZE, self::MAX_PAYLOAD_SIZE));
        }
    }

    public function makeID(int $length): string
    {
        $text = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        for ($i = 0; $i < $length; $i++) {
            $text .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $text;
    }

    public function formatZeroPad(int $num, int $count): string
    {
        $numStr = (string) (pow(10, $count) + $num);
        return substr($numStr, 1);
    }



}
