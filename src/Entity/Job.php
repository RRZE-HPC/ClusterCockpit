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

namespace App\Entity;

use AppBundle\Entity\Node;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
*  @ORM\Entity(repositoryClass="App\Repository\JobRepository")
*  @ORM\Table(name="job",indexes={@ORM\Index(name="search_idx", columns={"is_running","cluster_id"})})
*/
class Job
{
    /**
     *  @ORM\Column(type="integer")
     *  @ORM\Id
     *  @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     *  @ORM\Column(type="string")
     */
    private $jobId;

    /**
     *  @ORM\ManyToOne(targetEntity="User")
     */
    private $user;

    /**
     *  @ORM\ManyToOne(targetEntity="Cluster")
     */
    private $cluster;

    /**
     *  @ORM\Column(type="integer")
     */
    public $numNodes;

    /**
     *  @ORM\Column(type="integer")
     */
    public $startTime;

    /**
     *  @ORM\Column(type="integer", nullable=true)
     */
    public $duration;

    /**
     *  @ORM\Column(type="integer", nullable=true, options={"default":0})
     */
    public $severity;

    /**
     * @ORM\ManyToMany(targetEntity="Node", indexBy="id")
     * @ORM\JoinTable(name="jobs_nodes", joinColumns={@ORM\JoinColumn(name="job_id", referencedColumnName="id")}, inverseJoinColumns={@ORM\JoinColumn(name="node_id", referencedColumnName="id")})
     */
    private $nodes;


    public $jobCache;

    /**
     *  @ORM\Column(type="boolean")
     */
    public $isRunning;

    /**
     *  @ORM\Column(type="boolean", options={"default":false})
     */
    public $isCached;

    /**
     *  @ORM\Column(type="text", nullable=true)
     */
    private $jobScript;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    public $slot_0;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    public $slot_1;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    public $slot_2;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    public $slot_3;

    /**
     *  @ORM\Column(type="float", nullable=true)
     */
    public $slot_4;

    public $hasProfile;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\JobTag", inversedBy="jobs")
     */
    private $tags;


    public function __construct() {
        $this->nodes = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function setJobId($jobId)
    {
        $this->jobId = $jobId;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function getCluster()
    {
        return $this->cluster;
    }

    public function setCluster($cluster)
    {
        $this->cluster = $cluster;
    }

    public function getNumNodes()
    {
        return $this->numNodes;
    }

    public function setNumNodes($numNodes)
    {
        $this->numNodes = $numNodes;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function setStartTime($startTime)
    {
        $this->startTime = $startTime;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    public function getStopTime()
    {
        return $this->startTime + $this->duration;
    }

    public function getNodeIdArray()
    {
        $arr;

        foreach ( $this->nodes as $node ) {
            $arr[] = $node->getNodeId();
        }

        return $arr;
    }

    public function getNodeNameArray()
    {
        $arr;

        foreach ( $this->nodes as $node ) {
            $arr[] = $node->getNodeId();
        }

        return $arr;
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function addNode($node)
    {
        if ($this->nodes->contains($node)) {
            return $node;
        }

        $this->nodes[] = $node;
    }

    public function removeNode($node)
    {
        $this->nodes->removeElement($node);
    }

    public function getNode($id)
    {
        if (!isset($this->nodes[$id])) {
            throw new \InvalidArgumentException("No node with id $id in Job.");
        }

        return $this->nodes[$id];
    }

    public function getJobScript()
    {
        return $this->jobScript;
    }

    public function setJobScript($jobScript)
    {
        $this->jobScript = $jobScript;
    }

    public function isRunning()
    {
        return $this->isRunning;
    }

    /**
     * @return Collection|JobTag[]
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function getTagsArray()
    {
        $tags = array();

        foreach ($this->tags as $tag){
            $tags[] = array(
                'name' => $tag->getName(),
                'id' => $tag->getId(),
                'type' => $tag->getType()
            );
        }
        return $tags;
    }

    public function addTag(JobTag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    public function removeTag(JobTag $tag): self
    {
        if ($this->tags->contains($tag)) {
            $this->tags->removeElement($tag);
        }

        return $this;
    }
}
