<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace App\Service;

use App\Entity\Job;
use App\Entity\Plot;
use App\Entity\Data;
use App\Entity\NodeStat;
use App\Entity\StatisticCache;
use App\Repository\DoctrineMetricDataRepository;
use App\Repository\InfluxDBMetricDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class JobCache
{
    private $_em;
    private $_cache;
    private $_plotGenerator;
    private $_tsHelper;
    private $_metricDataRepository;

    public function __construct(
        TimeseriesHelper $tsHelper,
        EntityManagerInterface $em,
        PlotGenerator $plotGenerator,
        InfluxDBMetricDataRepository $metricRepo,
        /* DoctrineMetricDataRepository $metricRepo, */
        AdapterInterface $cache
    )
    {
        $this->_tsHelper = $tsHelper;
        $this->_em = $em;
        $this->_plotGenerator = $plotGenerator;
        $this->_metricDataRepository = $metricRepo;
        $this->_cache = $cache;
    }

    private function _initJob($job)
    {
        if ( $job->isRunning()) {
            $job->duration = time() - $job->startTime;
        }
    }

    private function _colorBackground(&$options, $metric, $stats)
    {
        $metricName = $metric->name;

        if ( ! is_null($metric->alert) ){
            if ( isset($stats["{$metricName}_avg"]) ) {
                if ( $metricName === 'mem_used' ){
                    if ( $stats["{$metricName}_avg"] > $metric->alert ){
                        $options['bgColor'] = 'rgb(255,238,230)';
                    } else if ( $stats["{$metricName}_avg"] > $metric->caution ){
                        $options['bgColor'] = 'rgb(255,255,230)';
                    } else {
                        unset($options['bgColor']);
                    }

                } else {
                    if ( $stats["{$metricName}_avg"] < $metric->alert ){
                        $options['bgColor'] = 'rgb(255,238,230)';
                    } else if ( $stats["{$metricName}_avg"] < $metric->caution ){
                        $options['bgColor'] = 'rgb(255,255,230)';
                    } else {
                        unset($options['bgColor']);
                    }
                }
            }
        } else {
            unset($options['bgColor']);
        }
    }

    private function _computeSeverity($job, $sortMetrics)
    {
        $severity = 0;

        $metrics = $sortMetrics['metrics'];
        $info = $sortMetrics['info'];
        $memBwMetric = $metrics['mem_bw'];
        $memBwSlot = 'slot_'.$info['mem_bw']->getSlot();
        $flopsAnyMetric = $metrics['flops_any'];
        $flopsAnySlot = 'slot_'.$info['flops_any']->getSlot();

        if ( $job->{$memBwSlot} < $memBwMetric->alert  and
            $job->{$flopsAnySlot} < $flopsAnyMetric->alert ){
            $severity += 400;
        } else if ( $job->{$memBwSlot} < $memBwMetric->caution  and
            $job->{$flopsAnySlot} < $flopsAnyMetric->caution ){
            $severity += 200;
        } else if ( $job->{$flopsAnySlot} < $flopsAnyMetric->alert ){
            $severity += 100;
        } else if ( $job->{$memBwSlot} < $memBwMetric->alert ){
            $severity += 100;
        }

        $severity += $job->getNumNodes();
        $severity += $job->getDuration()/3600;

        $job->severity = $severity ;
    }

    private function _computeAverages($job, $sortMetrics)
    {
        $stats = $this->_metricDataRepository->getJobStats(
            $job, $sortMetrics['metrics']);

        foreach ( $sortMetrics['info'] as $sortInfo ){
            $name = $sortInfo->getAccessKey();
            $slot = 'slot_'.$sortInfo->getSlot();
            $job->{$slot} = $stats[$name.'_avg'];
        }
    }

    private function _generatePlots(
        $job,
        $mode,
        $options,
        $live = false
    )
    {
        $job->jobCache = new \App\Entity\JobCache();

        if ( $mode === 'view' or $live == false ){
            if ( $options['plot_view_showPolarplot'] == 'true' or
                $options['plot_view_showStatTable'] == 'true' or
                $options['plot_general_colorBackground'] == 'true'
            ) {
                /* collect all metrics required for node table and job table sorting */
                $metrics = $job->getCluster()->getMetricList('stat')->getMetrics();
                $stats = $this->_metricDataRepository->getJobStats($job, $metrics);
            }
        }

        if ( $mode === 'view' ) { /* Single Job View */
            $options['mode'] = 'view';
            $options['autotick'] = true;
            $options['sample'] = 0;
            $options['legend'] = false;

            if ( $options['plot_view_showRoofline'] == 'true' ) {
                $this->_plotGenerator->generateJobRoofline(
                    $job, $this->_metricDataRepository->getJobRoofline($job, $metrics)
                );
            }

            if ( $options['plot_view_showPolarplot'] == 'true' ) {
                $this->_plotGenerator->generateJobPolarPlot(
                    $job, $metrics, $stats
                );
            }

            if ( $options['plot_view_showStatTable'] == 'true' ) {
                $job->jobCache->nodeStat = $stats['nodeStats'];
            } else {
                $job->jobCache->nodeStat = false;
            }
        } else if ( $mode === 'list' ) { /* Job list  */
            $options['mode'] = 'list';
            $options['sample'] = $options['plot_list_samples'];
            $options['legend'] = false;
        }

        $options['lineWidth'] =  $options['plot_general_lineWidth'];

        $metrics = $job->getCluster()->getMetricList($mode)->getMetrics();
        $data = $this->_metricDataRepository->getMetricData( $job, $metrics, $options);

        if ( $data == false ) {
            $job->hasProfile = false;
            return;
        }

        foreach ($metrics as $metric){

            if ( $mode === 'view' or $live == false ) {
                if ( $options['plot_general_colorBackground'] === 'true' ) {
                    $this->_colorBackground($options, $metric, $stats);
                }
            }

            $this->_plotGenerator->generateMetricPlot(
                $job,
                $metric,
                $options,
                $data
            );
        }
    }

    public function getArchive($job)
    {
        if ( ! $job->isRunning()) {
            if ( $this->_metricDataRepository->hasProfile($job) ) {
                $job->jobCache = new \App\Entity\JobCache();
                $metrics = $job->getCluster()->getMetricList('view')->getMetrics();
                $data = $this->_metricDataRepository->getMetricData(
                    $job, $metrics, array('sample' => 0));
                $nodes = $job->getNodes();

                foreach ($metrics as $metric) {
                    $metricName = $metric->getName();
                    $plot = new  Plot();
                    $plot->name = $metricName;
                    $lineData  = array();

                    foreach ($nodes as $node) {
                        $nodeId = $node->getNodeId();
                        $lineData[] = array(
                            'x'     => $data[$metricName][$nodeId]['x'],
                            'y'     => $data[$metricName][$nodeId]['y'],
                            'name'  => $node->getNodeId()
                        );
                    }

                    $plot->setOptions(array(
                        'unit' => $metric->getUnit(),
                        'timestep' => $metric->sampletime
                    ));
                    $plot->setData($lineData);
                    $job->jobCache->addPlot($plot);
                }

            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getBackend()
    {
        return $this->_plotGenerator->getBackend();
    }


    public function getUserStatistic(
        $userId,
        $control,
        $cluster,
        &$status
    )
    {
        $stat = new StatisticCache();

        $tmp = $this->_em->getRepository(
            \App\Entity\Job::class)->findStatByUser($userId, $control);
        $stat->setYear($control->getYear());
        $stat->setMonth($control->getMonth());
        $stat->setClusterId($control->getCluster());
        $stat->setUserId($userId);

        $stat->jobCount = $tmp['stat']['jobCount'] ;
        $stat->totalWalltime = $tmp['stat']['totalWalltime'];
        $stat->totalCoreHours = $tmp['stat']['totalCoreHours'];

        $this->_plotGenerator->generateJobHistograms($stat, $tmp);
        /* $data = $repository->fetchJobCloudData($userId, $control); */
        /* $this->_plotGenerator->createJobCloud($stat,$cluster, $data, $status); */
        return $stat;
    }

    public function warmupCache(
        $job,
        $options
    )
    {
        $this->_initJob($job);

        if (!$job->getNodes()->first()){
            return;
        }

        $viewMetrics = $job->getCluster()->getMetricList('view')->getMetrics();
        $pointsJob = $this->_metricDataRepository->getMetricCount($job, $viewMetrics);
        $points = (int) $options['data_cache_numpoints'];

        if ( $pointsJob > $points ){
            $this->_generatePlots($job, 'view', $options);
            $item = $this->_cache->getItem($job->getJobId().'view');
            $this->_cache->save($item->set($job->jobCache));

            $job->jobCache = NULL;
            $this->_generatePlots($job, 'list', $options);
            $item = $this->_cache->getItem($job->getJobId().'list');
            $this->_cache->save($item->set($job->jobCache));
            $job->isCached = true;
        }

        $tableSortRepository = $this->_em->getRepository(
            \App\Entity\TableSortConfig::class);
        $sortMetrics = $tableSortRepository->findDataMetrics($job);
        $this->_computeAverages($job, $sortMetrics);
        $this->_computeSeverity($job, $sortMetrics);
    }

    public function dropCache(
        $job
    )
    {
        $this->_initJob($job);

        if ( $job->isCached ){

            $key = $job->getJobId().'view';
            $this->_cache->deleteItem($key);

            $key = $job->getJobId().'list';
            $this->_cache->deleteItem($key);

            $job->isCached = false;
        }
    }

    public function hasCache(
        $job,
        $mode
    )
    {
        $this->_initJob($job);
        $item = $this->_cache->getItem($job->getJobId().$mode);

        if ($item->isHit()) {
            return true;
        } else {
            return false;
        }
    }

    public function checkCache(
        $job,
        $mode,
        $options
    )
    {
        $this->_initJob($job);
        $item = $this->_cache->getItem($job->getJobId().$mode);

        if ($item->isHit()) {
            $job->jobCache = $item->get();
            $job->hasProfile = true;
            return;
        }

        if ( $this->_metricDataRepository->hasProfile($job)){
            $this->_generatePlots($job, $mode, $options, true);
        }
    }
}
