# WebCrawler

A web crawler app that crawls web pages in
parallel and updates website graph in realtime. Supports domain/website graph views, dynamically
subgraph selection, execution scheduling, regular expression for website crawling and more.
Uses RabbitMQ for scheduling, AMPHP Parallel for parallel web node crawling, Mercure for live updates,
JS+Chart.js for graphing and EasyAdmin for UI panel, also PostgreSQL, OpenAPI with support of
GraphQL


## Running

```
docker compose up -d
```

## Deployment

[Symfony Docker docs](https://github.com/dunglas/symfony-docker/tree/main/docs)

[RabbitMQ deployment](https://symfony.com/doc/6.2/the-fast-track/en/32-rabbitmq.html#deploying-rabbitmq)
