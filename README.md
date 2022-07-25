# Redis queue
- CLI, який прийме повідомлення
- Воркер, який виведе повідомлення в певний час

## Running the environment
- Build the app image with the following command:

```bash
docker-compose build app
```

- When the build is finished, you can run the environment in background mode with:

```bash
docker-compose up -d
```

- To show information about the state of your active services, run:

```bash
docker-compose ps
```

You can use the `docker-compose exec` command to execute commands in the service containers, such as an `ls -l` to show detailed information about files in the application directory:

```bash
docker-compose exec app ls -l
```

```bash
docker-compose logs nginx
```

- If you want to pause your Docker Compose environment while keeping the state of all its services, run:

```bash
docker-compose pause
```

- You can then resume your services with:

```bash
docker-compose unpause
```

- To shut down your Docker Compose environment and remove all of its containers, networks, and volumes, run:

```bash
docker-compose down
```
