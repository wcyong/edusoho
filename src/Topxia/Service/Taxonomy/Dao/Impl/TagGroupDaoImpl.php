<?php

namespace Topxia\Service\Taxonomy\Dao\Impl;

use Topxia\Service\Common\BaseDao;
use Topxia\Service\Taxonomy\Dao\TagGroupDao;

class TagGroupDaoImpl extends BaseDao implements TagGroupDao
{
    protected $table = 'tag_group';

    public function get($id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
        return $this->getConnection()->fetchAssoc($sql, array($id));
    }

    public function create($fields)
    {
        $affected = $this->getConnection()->insert($this->table, $fields);

        if ($affected <= 0) {
            throw $this->createDaoException('Insert tag error.');
        }

        $this->clearCached();
        return $this->get($this->getConnection()->lastInsertId());
    }

    public function delete($id)
    {
        $result = $this->getConnection()->delete($this->table, array('id' => $id));
        $this->clearCached();
        return $result;
    }

    public function update($id, $fields)
    {
        $this->getConnection()->update($this->table, $fields, array('id' => $id));
        $this->clearCached();
        return $this->get($id);

    }

    public function search($conditions, $order, $start, $limit)
    {
        $builder = $this->_createSearchQueryBuilder($conditions)
            ->select('*')
            ->orderBy($orderBy[0], $orderBy[1])
            ->setFirstResult($start)
            ->setMaxResults($limit);

        return $builder->execute()->fetchAll() ?: array();
    }

    protected function _createSearchQueryBuilder($conditions)
    {
        if (empty($conditions['scope'])) {
            unset($conditions['scope']);
        }

        $builder = $this->createDynamicQueryBuilder($conditions)
            ->from($this->table)
            ->andWhere('name = :name')
            ->andWhere('scope = :scope');

        return $builder;
    }
}
