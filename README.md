TJ Queue is a generic job queue / message queue for Joomla. Messages queues are a way to delegate some processes to the background instead of performing them in a user blocking operation. It is also useful to background heavy processing. 

This extension uses the [php-enqueue](https://github.com/php-enqueue/enqueue-dev) library and provides wrappers for producing messages and consuming them. Currently MySQL and Amazon SQS are supported queue handlers.
