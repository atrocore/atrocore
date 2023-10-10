<?php

namespace Espo\Jobs;

use Atro\Core\Container;
use Espo\Core\Jobs\Base;

class DeleteForever extends  Base
{
    private $db;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->db = $this->getConfig()->get('database')['dbname'];
    }

    public function run($data, $targetId, $targetType, $scheduledJobId){
        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $scheduledJobId);

        if(empty($scheduledJob) ){
            return true;
        }
        $date = (new \DateTime())->modify("-{$scheduledJob->get('minimum_age')} day")->format('Y-m-d');
        $tables = $this->getEntityManager()->nativeQuery('show tables')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if ($table == 'attachment') {
                continue 1;
            }
            $columns = $this->getEntityManager()->nativeQuery("SHOW COLUMNS FROM {$this->db}.$table")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('deleted', $columns)) {
                continue 1;
            }
            if (!in_array('modified_at', $columns)) {
                $this->exec("DELETE FROM {$this->db}.$table WHERE deleted=1");
            } else {
                $this->exec("DELETE FROM {$this->db}.$table WHERE deleted=1 AND DATE(modified_at)<'{$date}'");
            }
        }

        return true;
    }


    /**
     * @param string $sql
     */
    protected function exec(string $sql): void
    {
        try {
            $this->getEntityManager()->nativeQuery($sql);
        } catch (\PDOException $e) {
            $GLOBALS['log']->error('DeleteForever: ' . $e->getMessage() . ' | ' . $sql);
        }
    }


}