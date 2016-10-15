<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Source
 *
 * @ORM\Table(name="source")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\SourceRepository")
 */
class Source
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="base_url", type="string", length=2000)
     */
    private $baseUrl;

    /**
     * @var string
     *
     * @ORM\Column(name="query_url", type="string", length=2000)
     */
    private $queryUrl;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Source
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set baseUrl
     *
     * @param string $baseUrl
     *
     * @return Source
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Get baseUrl
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set queryUrl
     *
     * @param string $queryUrl
     *
     * @return Source
     */
    public function setQueryUrl($queryUrl)
    {
        $this->queryUrl = $queryUrl;

        return $this;
    }

    /**
     * Get queryUrl
     *
     * @return string
     */
    public function getQueryUrl()
    {
        return $this->queryUrl;
    }
}

