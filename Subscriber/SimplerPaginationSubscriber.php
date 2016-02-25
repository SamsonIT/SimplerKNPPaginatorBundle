<?php

namespace Samson\Bundle\SimplerKNPPaginatorBundle\Subscriber;

use DataDog\PagerBundle\Pagination;
use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\Event\ItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

class SimplerPaginationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'knp_pager.items' => array('items', 2 ^ 16)
        );
    }

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Classname to override the pagination
     */
    private $paginationOverrideClassname = null;

    private $defaultPaginationClassname = 'DataDog\PagerBundle\Pagination';

    private $paginationExtraOptions = [];

    public function __construct()
    {

    }

    public function addPaginationExtraOption($key, $value)
    {
        $this->paginationExtraOptions[$key] = $value;
    }

    /**
     * @param Classname $paginationOverrideClassname
     */
    public function setPaginationOverrideClassname($paginationOverrideClassname)
    {
        $this->paginationOverrideClassname = $paginationOverrideClassname;
    }

    public function resetOverrides()
    {
        $this->paginationOverrideClassname = null;
        $this->paginationExtraOptions = [];
    }

    /**
     * @param Request $request
     */
    public function setRequest($request = null)
    {
        $this->request = $request;
    }

    /**
     * @param ItemsEvent $event
     */
    public function items(ItemsEvent $event)
    {
        // Check if the request exists, for now it's needed because Pagination() needs is.
        // Maybe refactor Pagination one day and keep the good parts and get rid of the $request as it's a (nasty) dependency.
        if (!$this->request instanceof Request) {
            throw new \RuntimeException('Request must be set in ' . __CLASS__);
        }
        $target = $event->target;
        // for now only accept querybuilders.
        if (!$target instanceof QueryBuilder) {
            throw new \UnexpectedValueException();
        }
        $qb = $event->target;

        $filterFieldParameterName = array_key_exists('filterFieldParameterName', $event->options) ?
            $event->options['filterFieldParameterName'] : 'filterField';
        $filterValueParameterName = array_key_exists('filterValueParameterName', $event->options) ?
            $event->options['filterValueParameterName'] : 'filterValue';

        $this->appendQuery($qb, $this->request->query->get($filterValueParameterName, []), $this->request->query->get($filterFieldParameterName, []));

        $options = ['page' => $event->getOffset(), 'limit' => $event->getLimit()];
        if ($this->request->query->has('sort')) {
            $options['sorters'] = [$this->request->query->get('sort') => $this->request->query->get('direction')];
        } elseif (array_key_exists('defaultSortFieldName', $event->options)) {
            $options['sorters'] = [$event->options['defaultSortFieldName'] => $event->options['defaultSortDirection']];
        }

        $classname = $this->paginationOverrideClassname ? $this->paginationOverrideClassname : $this->defaultPaginationClassname;
        $options = array_merge($options, $this->paginationExtraOptions);

        $items = new $classname($qb, $this->request, $options);
        $event->count = $items->total();
        $event->items = iterator_to_array($items);
        $event->stopPropagation();
    }

    /**
     * Taken from autocomplete-bundle
     * @see https://github.com/SamsonIT/AutocompleteBundle/blob/master/Query/ResultsFetcher.php
     *
     * @param QueryBuilder $qb
     * @param array $searchWords
     * @param array $searchFields
     */
    public function appendQuery(QueryBuilder $qb, array $searchWords, array $searchFields)
    {
        foreach ($searchWords as $key => $searchWord) {
            $expressions = array();

            foreach ($searchFields as $key2 => $field) {
                $expressions[] = $qb->expr()->like($field, ':query' . $key . $key2);
                $qb->setParameter('query' . $key . $key2, '%' . $searchWord . '%');
            }
            $qb->andWhere("(" . call_user_func_array(array($qb->expr(), 'orx'), $expressions) . ")");
        }
    }

}