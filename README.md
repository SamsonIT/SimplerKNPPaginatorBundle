SimplerKNPPaginator
=====
KNP Paginator Evensubscriber to paginate stuff fast.

How it works
===
The class `SimplerPaginationSubscriber` is a listener on the `'knp_pager.items'`-event.

Usage
===
At the moment the `SimplerPaginationSubscriber` is only a service and is not connected to Symfony's event-system. This can easily be achieved by extending this bundle and writing a `CompilerPass`.

For now it can be used like this, e.g. in your Controller:
```php
$this->get('knp_paginator')->subscribe($this->get('samson_simple_knppaginator.subscriber.simpler_pagination_subscriber'));
$this->get('knp_paginator')->paginate($qb... );
```

