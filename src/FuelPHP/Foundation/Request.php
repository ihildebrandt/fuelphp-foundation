<?php
/**
 * Part of the FuelPHP framework.
 *
 * @package    FuelPHP\Foundation
 * @version    2.0
 * @license    MIT License
 * @copyright  2010 - 2013 Fuel Development Team
 */

namespace FuelPHP\Foundation;

/**
 * FuelPHP Request class
 *
 * Initiate a request from the URI passed
 *
 * @package  FuelPHP\Foundation
 *
 * @since  2.0.0
 */
class Request
{
	/**
	 * @var  Environment
	 *
	 * @since  2.0.0
	 */
	protected $env;

	/**
	 * @var  Application  app that created this request
	 *
	 * @since  2.0.0
	 */
	protected $app;

	/**
	 * @var  string
	 *
	 * @since  2.0.0
	 */
	protected $requestUri = '';

	/**
	 * @var  \Fuel\Kernel\Request\Input\Base
	 *
	 * @since  2.0.0
	 */
	protected $input;

	/**
	 * @var  array  associative array of named params in the URI
	 *
	 * @since  1.0.0
	 */
	protected $params;

	/**
	 * @var  Response  Response after execution
	 *
	 * @since  1.0.0
	 */
	protected $response;

	/**
	 * @var  array  active Request stack before activation of this one
	 *
	 * @since  2.0.0
	 */
	protected $activeRequests = array();

	/**
	 * Constructor
	 *
	 * @param  string  $resource
	 * @param  array|Input  $input
	 *
	 * @since  1.0.0
	 */
	public function __construct($resource = '', $input = null)
	{
		$this->env = \FuelPHP::resolve('Environment');
		$this->app = $this->env->getActiveApplication();

		$this->requestUri  = '/'.trim(strval($resource), '/');

		// Create the new Input object when an array was passed
		if (is_array($input))
		{
			$this->input = \FuelPHP::resolve('Input', null, $input);
		}

		// If there's no input object: default to environment input
		if ( ! $this->input)
		{
			$this->input = ($req = $app->getActiveRequest()) ? $req->input : $this->env->input;
		}
	}

	/**
	 * Execute the request
	 *
	 * Must use $this->activate() as the first statement and $this->deactivate() as the last one
	 *
	 * @return  Request
	 * @throws  \Fuel\Kernel\Response\Exception\Redirect|\Exception
	 * @throws  \DomainException
	 *
	 * @since  1.0.0
	 */
	public function execute()
	{
		$this->activate();

		list($this->controller, $this->controllerParams, $this->params) = $this->app->getRouter()->route($this->requestUri);

		try
		{
			if ( ! is_callable($this->controller))
			{
				throw new \DomainException('The Controller returned by routing is not callable.');
			}

			// Provide request context to controller when possible
			if (is_object($this->controller) and method_exists($this->controller, '_setRequest'))
			{
				$this->controller->_setRequest($this);
			}

			try
			{
				$this->response = call_user_func($this->controller, $this->controllerParams);
			}
			catch (Response\Exception\Redirect $e)
			{
				$this->response = $e->response($this->app);
			}

			if ( ! is_object($this->getResponse()) or array_diff(
					get_class_methods('Response'),
					get_class_methods($this->getResponse())
				) != array())
			{
				throw new \DomainException('Result object from a Controller must'.
					' implement all methods from Response.');
			}

			// Render body before finishing the Request when a Viewable was returned
			if (($body = $this->response->getContent()) instanceof Viewable)
			{
				$this->response->setContent($body->render());
			}
		}
		catch (\Exception $e)
		{
			// deactivate and rethrow
			$this->deactivate();
			throw $e;
		}

		$this->deactivate();

		return $this;
	}

	/**
	 * Fetch a named parameter from the request URI
	 *
	 * @param   null|string  $param
	 * @param   mixed        $default
	 * @return  array
	 *
	 * @since  1.0.0
	 */
	public function getParam($param = null, $default = null)
	{
		if (is_null($param))
		{
			return $this->params;
		}

		return isset($this->params[$param]) ? $this->params[$param] : $default;
	}

	/**
	 * Makes this Request the active one
	 *
	 * @return  Base  for method chaining
	 *
	 * @since  2.0.0
	 */
	public function activate()
	{
		array_push($this->activeRequests, $this->app->getActiveRequest($this));
		$this->app->setActiveRequest($this);

		return $this;
	}

	/**
	 * Deactivates this Request and reactivates the previous active
	 *
	 * @return  Base  for method chaining
	 *
	 * @since  2.0.0
	 */
	public function deactivate()
	{
		$this->app->setActiveRequest(array_pop($this->activeRequests));

		return $this;
	}

	/**
	 * Fetch the request response after execution
	 *
	 * @return  \Fuel\Kernel\Response\Base
	 *
	 * @since  1.0.0
	 */
	public function getResponse()
	{
		return $this->response;
	}

	/**
	 * Returns this requests Input instance
	 *
	 * @return  Base
	 *
	 * @since  1.1.0
	 */
	public function getInput()
	{
		return $this->input;
	}

	/**
	 * Returns the request that created this one
	 *
	 * @return  Base
	 *
	 * @since  1.1.0
	 */
	public function getParent()
	{
		return $this->parent;
	}
}
