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

namespace App\Controller;

use App\Entity\Job;
use App\Entity\JobSearch;
use App\Entity\User;
use App\Entity\UnixGroup;
use App\Entity\UpdateGroupRequest;
use App\Entity\StatisticsControl;
use App\Service\JobCache;
use App\Service\PlotGenerator;
use App\Service\GroupFacade;
use App\Form\UnixGroupType;
use App\Form\StatisticsControlType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Finder\Finder;

class UserViewController extends Controller
{
    public function list(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository(\App\Entity\User::class);
        $users = $repository->findAll();

        return $this->render('users/listUsers.html.twig',
            array(
                'users' => $users,
            ));
    }

    public function showGroup(
        UnixGroup $group,
        Request $request)
    {
        $year = $request->query->get('year');
        $month = $request->query->get('month');
        $cluster = $request->query->get('cluster');

        $control = new StatisticsControl();

        if ( $year ) {
            $control->setYear($year);
        } else {
            $control->setYear(date("Y"));
        }
        if ( $month ) {
            $control->setMonth($month);
        } else {
            $control->setMonth(date("m"));
        }
        if ( $cluster ) {
            $control->setCluster($cluster);
        } else {
            $control->setCluster(1);
        }

        $form = $this->createForm(StatisticsControlType::class, $control);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $control = $form->getData();
        }

        $repository = $this->getDoctrine()->getRepository(\App\Entity\User::class);
        $jobRepository = $this->getDoctrine()->getRepository(\App\Entity\Job::class);
        $users = $repository->findByGroupId($group->getId(), $control);
        $usersD = array();

        foreach ( $users as $user ){
            $usersD[] = array(
                'meta' => $user,
                'stat' => $jobRepository->findStatByUser($user->getId(), $control, false),
            );
        }

        $settings = $jobRepository->getSettings($control);
        $stat = $jobRepository->findStatByGroup($group->getId(), $settings);

        return $this->render('users/showGroup.html.twig',
            array(
                'form' => $form->createView(),
                'group' => $group,
                'control' => $control,
                'stat' => $stat['stat'],
                /* 'histo_runtime' => $stat['histoPlotRuntime']['trace'], */
                /* 'layout_runtime' => $stat['histoPlotRuntime']['layout'], */
                /* 'histo_numnodes' => $stat['histoPlotNumnodes']['trace'], */
                /* 'layout_numnodes' => $stat['histoPlotNumnodes']['layout'], */
                'users' => $usersD,
            ));
    }

    public function editGroup(UnixGroup $group, GroupFacade $groupFacade, Request $request)
    {
        $updateGroupRequest = UpdateGroupRequest::fromUnixGroup($group);

        $form = $this->createForm(UnixGroupType::class, $updateGroupRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $groupFacade->updateUnixGroup(
                $group,
                $updateGroupRequest->organisation,
                $updateGroupRequest->contact
            );

            return $this->redirectToRoute('show_group',array('id'=>$group->getId()));
        }

        return $this->render('users/editGroup.html.twig',
            array(
                'form' => $form->createView(),
            ));
    }


    public function show(
        User $user,
        JobCache $jobCache,
        SerializerInterface $serializer,
        Request $request)
    {
        $year = $request->query->get('year');
        $month = $request->query->get('month');
        $cluster = $request->query->get('cluster');

        $status = array(
            'hasPerf' => true,
            'hasJobs' => true
        );

        $control = new StatisticsControl();

        if ( $year ) {
            $control->setYear($year);
        } else {
            $control->setYear(date("Y"));
        }
        if ( $month ) {
            $control->setMonth($month);
        } else {
            $control->setMonth(date("m"));
        }
        if ( $cluster ) {
            $control->setCluster($cluster);
        } else {
            $control->setCluster(1);
        }

        $search = new JobSearch();
        $search->setNumNodesFrom( 0 );
        $search->setNumNodesTo( 64 );
        $search->setDurationFrom( 0 );
        $search->setDurationTo( 99999999 );
        $search->setUserId($user->getId());

        $datestring = sprintf("%04d%02d01",$control->getYear(),$control->getMonth());
        $startTime = strtotime($datestring);
        $days = date(' t ', $startTime );
        $datestring = sprintf("%04d%02d%02d",$control->getYear(), $control->getMonth(), $days);
        $stopTime = strtotime($datestring);

        $search->setDateFrom($startTime);
        $search->setDateTo($stopTime);
        $search->setClusterId($control->getCluster());

        $form = $this->createForm(StatisticsControlType::class, $control);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $control = $form->getData();

            $datestring = sprintf("%04d%02d01",$control->getYear(),$control->getMonth());
            $startTime = strtotime($datestring);
            $days = date(' t ', $startTime );
            $datestring = sprintf("%04d%02d%02d",$control->getYear(), $control->getMonth(), $days);
            $stopTime = strtotime($datestring);

            $search->setDateFrom($startTime);
            $search->setDateTo($stopTime);
            $search->setClusterId($control->getCluster());
        }

        $statCache = $jobCache->checkStatisticCache(
            $user->getId(),
            $control,
            $control->getCluster(),
            $status);

        return $this->render('users/showUser.html.twig',
            array(
                'form' => $form->createView(),
                'user' => $user,
                'status' => $status,
                'stat'  => $statCache,
                'backend' => $jobCache->getBackend(),
                'jobSearch' => $serializer->serialize($search, 'json')
                ));
    }
}
