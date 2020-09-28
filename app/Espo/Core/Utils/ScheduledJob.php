<?php

namespace Espo\Core\Utils;

use Espo\Core\Exceptions\NotFound;

class ScheduledJob
{
    private $container;

    private $systemUtil;

    protected $data = null;

    protected $cacheFile = 'data/cache/application/jobs.php';

    protected $cronFile = 'cron.php';

    protected $allowedMethod = 'run';

    /**
     * Period to check if crontab is configured properly
     *
     * @var string
     */
    protected $checkingCronPeriod = '25 hours';

    /**
     * @var array - path to cron job files
     */
    private $paths = array(
        'corePath' => CORE_PATH . '/Espo/Jobs',
        'modulePath' => CORE_PATH . '/Espo/Modules/{*}/Jobs',
        'customPath' => 'custom/Espo/Custom/Jobs',
    );

    protected $cronSetup = array(
        'linux' => '* * * * * cd {DOCUMENT_ROOT}; {PHP-BIN-DIR} -f {CRON-FILE} > /dev/null 2>&1',
        'windows' => '{PHP-BINARY} -f {FULL-CRON-PATH}',
        'mac' => '* * * * * cd {DOCUMENT_ROOT}; {PHP-BIN-DIR} -f {CRON-FILE} > /dev/null 2>&1',
        'default' => '* * * * * cd {DOCUMENT_ROOT}; {PHP-BIN-DIR} -f {CRON-FILE} > /dev/null 2>&1',
    );

    public function __construct(\Treo\Core\Container $container)
    {
        $this->container = $container;
        $this->systemUtil = new \Espo\Core\Utils\System();
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }

    protected function getSystemUtil()
    {
        return $this->systemUtil;
    }

    public function getMethodName()
    {
        return $this->allowedMethod;
    }

    /**
     * Get list of all jobs
     *
     * @return array
     */
    public function getAll()
    {
        if (!isset($this->data)) {
            $this->init();
        }

        return $this->data;
    }

    /**
     * Get class name of a job by name
     *
     * @param  string $name
     * @return string
     */
    public function get($name)
    {
        return $this->getClassName($name);
    }

    public function getAvailableList()
    {
        $data = $this->getAll();

        $list = array_keys($data);

        return $list;
    }

    /**
     * Get list of all job names
     *
     * @return array
     */
    public function getAllNamesOnly()
    {
        $data = $this->getAll();

        $namesOnly = array_keys($data);

        return $namesOnly;
    }

    /**
     * Get class name of a job
     *
     * @param  string $name
     * @return string
     */
    protected function getClassName($name)
    {
        $name = Util::normilizeClassName($name);

        $data = $this->getAll();

        $name = ucfirst($name);
        if (isset($data[$name])) {
            return $data[$name];
        }

        return false;
    }

    /**
     * Load scheduler classes. It loads from ...Jobs, ex. \Espo\Jobs
     * @return null
     */
    protected function init()
    {
        $classParser = $this->getContainer()->get('classParser');
        $classParser->setAllowedMethods( array($this->allowedMethod) );
        $this->data = $classParser->getData($this->paths, $this->cacheFile);
    }

    public function getSetupMessage()
    {
        $language = $this->getContainer()->get('language');

        $OS = $this->getSystemUtil()->getOS();
        $desc = $language->translate('cronSetup', 'options', 'ScheduledJob');

        $data = array(
            'PHP-BIN-DIR' => $this->getSystemUtil()->getPhpBin(),
            'PHP-BINARY' => $this->getSystemUtil()->getPhpBinary(),
            'CRON-FILE' => $this->cronFile,
            'DOCUMENT_ROOT' => $this->getSystemUtil()->getRootDir(),
            'FULL-CRON-PATH' => Util::concatPath($this->getSystemUtil()->getRootDir(), $this->cronFile),
        );

        $message = isset($desc[$OS]) ? $desc[$OS] : $desc['default'];
        $command = isset($this->cronSetup[$OS]) ? $this->cronSetup[$OS] : $this->cronSetup['default'];

        foreach ($data as $name => $value) {
            $command = str_replace('{'.$name.'}', $value, $command);
        }

        return array(
            'message' => $message,
            'command' => $command,
        );
    }

    /**
     * Check if crontab is configured properly
     *
     * @return boolean
     */
    public function isCronConfigured()
    {
        $startDate = new \DateTime('-' . $this->checkingCronPeriod, new \DateTimeZone("UTC"));
        $endDate = new \DateTime('+' . $this->checkingCronPeriod, new \DateTimeZone("UTC"));

        $query = "
            SELECT job.id FROM scheduled_job
            LEFT JOIN job ON job.scheduled_job_id = scheduled_job.id AND job.deleted = 0
            WHERE
                scheduled_job.job = 'Dummy'
                AND scheduled_job.deleted = 0
                AND job.execute_time BETWEEN '". $startDate->format('Y-m-d H:i:s') ."' AND '". $endDate->format('Y-m-d H:i:s') ."'
                AND job.status IN ('Success', 'Failed', 'Pending')
            ORDER BY job.execute_time DESC
        ";

        $pdo = $this->getEntityManager()->getPDO();
        $sth = $pdo->prepare($query);
        $sth->execute();

        $row = $sth->fetch(\PDO::FETCH_ASSOC);

        if (!empty($row['id'])) {
            return true;
        }

        return false;
    }
}
