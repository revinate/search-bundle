<?php

namespace Revinate\SearchBundle\Lib\Search\ElasticSearch\RevinateElastica;

use Elastica\Client;
use Elastica\Exception\NotFoundException;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Elastica\Response;

/**
 * Class Template
 * @package Elastica
 * @link http://www.elastic.co/guide/en/elasticsearch/reference/current/indices-templates.html
 */
class Template
{
    /**
     * @var Client
     */
    protected $_client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->_client = $client;
    }

    /**
     * Retrieve a template by name
     * @param string $name the name of the desired template
     * @throws ResponseException
     * @throws NotFoundException
     * @return array
     */
    public function getTemplate($name)
    {
        try {
            $response = $this->request($name);
        } catch (ResponseException $e) {
            if ($e->getResponse()->getStatus() == 404) {
                throw new NotFoundException("Template '" . $name . "' does not exist.");
            }
            throw $e;
        }

        if ($response->getStatus() == 404) {
            throw new NotFoundException("Template '" . $name . "' does not exist.");
        }
        $data = $response->getData();
        return $data[$name];
    }

    /**
     * Retrieve all templates
     * @return array
     */
    public function getAllTemplates()
    {
        return $this->request()->getData();
    }

    /**
     * Create a new template
     * @param string $name the name of this template
     * @param string $template the template pattern
     * @param array $settings optional settings for this template
     * @param array $mappings optional mappings for this template
     * @param array $aliases optional aliases for this template
     * @return Response
     */
    public function createTemplate($name, $template, $settings = array(), $mappings = array(), $aliases = array())
    {
        $data = array(
            'template' => $template
        );
        if (!empty($settings)) {
            $data += array('settings' => $settings);
        }
        if (!empty($mappings)) {
            $data += array('mappings' => $mappings);
        }
        if (!empty($aliases)) {
            $data += array('aliases' => $aliases);
        }
        return $this->request($name, Request::PUT, $data);
    }

    /**
     * Delete a template
     * @param string $name the name of the template to be deleted
     * @return Response
     */
    public function deleteTemplate($name)
    {
        return $this->request($name, Request::DELETE);
    }

    /**
     * Checks whether a template exists
     * @param string $name the name of the template to be checked
     * @return Response
     */
    public function templateExists($name)
    {
        return $this->request($name, Request::HEAD);
    }

    /**
     * Perform a template request
     * @param string $path the URL
     * @param string $method the HTTP method
     * @param array $data request body data
     * @param array $query query string parameters
     * @return Response
     */
    public function request($path = null, $method = Request::GET, $data = array(), array $query = array())
    {
        return $this->_client->request("/_template/" . $path, $method, $data, $query);
    }
}