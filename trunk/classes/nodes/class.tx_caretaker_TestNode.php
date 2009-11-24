<?php
/**
 * This is a file of the caretaker project.
 * Copyright 2008 by n@work Internet Informationssystem GmbH (www.work.de)
 * 
 * @Author	Thomas Hempel 		<thomas@work.de>
 * @Author	Martin Ficzel		<martin@work.de>
 * @Author	Patrick Kollodzik	<patrick@work.de> 
 * @Author	Tobias Liebig   	<mail_typo3.org@etobi.de>
 * @Author	Christopher Hlubek	<hlubek@networkteam.com>
 * 
 * $Id$
 * 
 * @todo Check proper setup of $this->testService, use $this->testService instead of
 * local variable in tx_caretaker_TestNode::updateTestResult and tx_caretaker_TestNode::runTest
 * Use of $this->testService currently executes several tests with the same setup.
 */

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008 Martin Ficzel <ficzel@work.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

class tx_caretaker_TestNode extends tx_caretaker_AbstractNode {
	
	/**
	 * Test Service Type
	 * @var string
	 */
	private $test_service_type;
	
	/**
	 * Configuration of the test
	 * @var unknown_type
	 */
	private $test_service_configuration = false;

	/**
	 * Reference to the test service
	 * @var tx_caretaker_TestServiceBase
	 */
	private $test_service = null;
	
	/**
	 * Interval of Tests in Seconds
	 * @var integer
	 */
	private $test_interval  = false;

	/**
	 * Retry the test n times after failure or warning
	 * @var integer
	 */
	protected $test_retry = 0;

	/**
	 * The test shall be executed only after this hour
	 * @var integer
	 */
	private $start_hour = false;
	
	/**
	 * The test shall be executed only before this hour
	 * @var integer
	 */
	private $stop_hour  = false; 


	/**
	 * Constructor
	 * 
	 * @param integer $uid
	 * @param string $title
	 * @param tx_caretaker_AbstractNode $parent_node
	 * @param string $service_type
	 * @param string $service_configuration
	 * @param integer $interval
	 * @param integer $start_hour
	 * @param integer $stop_hour
	 * @param boolean $hidden
	 * @return tx_caretaker_TestNode
	 */
	public function __construct($uid, $title, $parent_node, $service_type, $service_configuration, $interval = 86400, $retry=0, $start_hour=false, $stop_hour=false, $hidden=FALSE ){

		// overwrite default test configuration
		$configurationOverlay = $parent_node->getTestConfigurationOverlayForTestUid($uid);
		if ($configurationOverlay) {
			$service_configuration = $configurationOverlay;
			if ($service_configuration['hidden']) {
				$hidden = true;
			}
		}

		parent::__construct($uid, $title, $parent_node, 'Test', $hidden);

			// create Test Service
		$info = t3lib_extMgm::findService('caretaker_test_service', $service_type );
		if ($info && $info['classFile'] ){
			$requireFile = t3lib_div::getFileAbsFileName($info['classFile']);
			if (@is_file($requireFile)) {
				t3lib_div::requireOnce ($requireFile);
				$this->test_service = t3lib_div::makeInstance($info['className']);
				if ($this->test_service) {
					$this->test_service->setInstance( $this->getInstance() );
					$this->test_service->setConfiguration($service_configuration);
				} else {
					throw new Exception('testservice class '.$info['className'].' could not be instanciated');
				}
			} else {
				throw new Exception('testservice '.$service_type.' class file '.$requireFile.' nout found');
			}
		} else {
			throw new Exception('caretaker testservice '.$service_type.' not found');
		}

		$this->test_service_type = $service_type;
		$this->test_service_configuration = $service_configuration;
		$this->test_interval = $interval;
		$this->test_retry    = $retry;
		$this->start_hour    = $start_hour;
		$this->stop_hour     = $stop_hour;
		
	}

	/**
	 * Get the caretaker node id of this node
	 * return string
	 */
	public function getCaretakerNodeId(){
		$instance = $this->getInstance();
		return 'instance_'.$instance->getUid().'_test_'.$this->getUid();
	}

	/**
	 * Get the description of the Testsevice
	 * @return string
	 */
	public function getTypeDescription(){
		if ( $this->test_service ) {
			return $this->test_service->getTypeDescription();
		}
	}

	/**
	 * Get the description of the Testsevice
	 * @return string
	 */
	public function getConfigurationInfo(){
		if ( $this->test_service ) {
			$configurationInfo = $this->test_service->getConfigurationInfo();
			if (is_array($this->test_service_configuration['overwritten_in'])) {
				$configurationInfo .= ' (overwritten in ' .
					'<span title=" '.
					$this->test_service_configuration['overwritten_in']['id'] .
					'">' .
					$this->test_service_configuration['overwritten_in']['title'] .
					'</span>)';
			}
			return $configurationInfo;
		}
	}

	public function getHiddenInfo(){
		$hiddenInfo = parent::getHiddenInfo();
		if ( $this->test_service ) {
			if (is_array($this->test_service_configuration['overwritten_in'])
			 && $this->test_service_configuration['hidden']) {
				$hiddenInfo .= ' (hidden in ' .
					'<span title=" '.
					$this->test_service_configuration['overwritten_in']['id'] .
					'">' .
					$this->test_service_configuration['overwritten_in']['title'] .
					'</span>)';
			}
		}
		return $hiddenInfo;
	}


	/**
	 * Get the Test Interval 
	 * @return unknown_type
	 */
	public function getInterval(){
		return $this->test_interval;
	}
	
	/**
	 * Get the test start hour
	 * @return unknown_type
	 */
	public function getStartHour(){
		return $this->start_hour;
	}
	
	/**
	 * Get the test stop hour
	 * @return unknown_type
	 */
	public function getStopHour(){
		return $this->stop_hour;
	}
	
	/**
	 * Update TestResult and store in DB. If the Test is not due the result is fetched from the cache.
	 * 
	 * If force is not set the execution time and exclude hours are taken in account.
	 * 
	 * @param boolean $force_update Force update of this test
	 * @return tx_caretaker_NodeResult
	 */
	public function updateTestResult($force_update = false){

		if ( $this->getHidden() == true ){
			$result = tx_caretaker_TestResult::undefined('Node is disabled');
			$this->log('disabled '.$result->getLocallizedStateInfo().' '.$result->getLocallizedInfotext().' '.$msg );
			return $result;
		}
		
		$test_result_repository = tx_caretaker_TestResultRepository::getInstance();
		$instance = $this->getInstance();
		
			// check cache and return
		if (!$force_update ){
			$result = $test_result_repository->getLatestByNode( $this );
			if ($result && $result->getTstamp() > time()-$this->test_interval ) {
				$this->log('cacheresult '.$result->getStateInfo().' '.$result->getValue().' '.$result->getLocallizedInfotext() );
				return $result;
			} else if ($this->start_hour > 0 || $this->stop_hour > 0 ) {
				$local_time = localtime(time(), true);
				$local_hour = $local_time['tm_hour'];
				if ($local_hour < $this->start_hour || $local_hour >= $this->stop_hour ){
					$this->log('cacheresult '.$result->getStateInfo().' '.$result->getValue().' '.$result->getLocallizedInfotext() );
					return $result;	
				}
			}
		}
		
		if($this->test_service && $this->test_service->isExecutable()) {
			
				// try to execute test
			try {
				$result = $this->test_service->runTest();
			} catch ( Exception $e ) {
				$result = new tx_caretaker_TestResult( TX_CARETAKER_STATE_ERROR , 0, '{LLL:EXT:caretaker/locallang_fe.xml:service_exception}'.$e->getMessage  );
			}
			
				// retry if not ok and retrying is enabled
			if ($result->getState() != 0 && $this->test_retry > 0){
				$round = 0;
				while ( $round < $this->test_retry && $result->getState() != 0 ){
					try {
						$result = $this->test_service->runTest();
					} catch ( Exception $e ) {
						$result = new tx_caretaker_TestResult( TX_CARETAKER_STATE_ERROR , 0, '{LLL:EXT:caretaker/locallang_fe.xml:service_exception}'.$e->getMessage  );
					}
					$round ++;
				}
				$result->addSubMessage( new tx_caretaker_ResultMessage( 'LLL:EXT:caretaker/locallang_fe.xml:retry_info' , array( 'number'=>$round )  ) );
			}

				// save to repository if the result differs from the last one
			$resultRepository = tx_caretaker_TestResultRepository::getInstance();
			$lastTestResult = $resultRepository->getLatestByNode($this);

			if ($lastTestResult->isDifferent($result) ){
				$resultRepository->saveTestResultForNode( $this, $result);
			}
			
				// trigger notification
			if ($result->getState() > 0){
				$this->sendNotification( $result->getState() , $result->getLocallizedInfotext() );
			} 

				// trigger log
			$this->log('update '.$result->getLocallizedStateInfo().' '.$result->getLocallizedInfotext().' '.$msg );
			
		} else {
			
			$result = $test_result_repository->getLatestByInstanceAndTest($instance, $this);
			$this->log('Service is busy... skipping test.');
		}
		
		return $result;
		
	}
	


	/**
	 * Get the all tests wich can be found below this node
	 * @return array
	 */
	public function getTestNodes(){
		return array($this);
	}
	

	/**
	 * Get the Value Description for this test
	 * @see caretaker/trunk/classes/nodes/tx_caretaker_AbstractNode#getValueDescription()
	 */
	public function getValueDescription() {
		
		$test_service = t3lib_div::makeInstanceService('caretaker_test_service',$this->test_service_type);
		
		if ($test_service){
			return $test_service->getValueDescription();
		} else {
			return 'unknown service '.$this->test_service_type;
		}
	}

	/**
	 * Get the current Test Result from Cache
	 *
	 * @see caretaker/trunk/classes/nodes/tx_caretaker_AbstractNode#getTestResult()
	 */
	public function getTestResult(){

		if ( $this->getHidden() == true ){
			$result = tx_caretaker_TestResult::undefined('Node is disabled');
			$this->log('disabled '.$result->getLocallizedStateInfo().' '.$result->getMessage()->getLocallizedInfotext().' '.$msg );
			return $result;
		}

		$instance  = $this->getInstance();
		$test_result_repository = tx_caretaker_TestResultRepository::getInstance();
		$result    = $test_result_repository->getLatestByNode($this);

		$this->log('cache '.$result->getStateInfo().' '.$result->getValue().' '.$result->getMessage()->getLocallizedInfotext() );

		return $result;
	}
	
	/**
	 * Get the TestResultRange for the given Timerange
	 * @see caretaker/trunk/classes/nodes/tx_caretaker_AbstractNode#getTestResultRange()
	 * @param $graph True by default. Used in the resultrange repository the specify the handling of the last result. For more information see tx_caretaker_testResultRepository.
	 */
	public function getTestResultRange($start_timestamp, $stop_timestamp, $graph = true){
		$instance  = $this->getInstance();
		$test_result_repository = tx_caretaker_TestResultRepository::getInstance();
		$resultRange = $test_result_repository->getRangeByNode( $this, $start_timestamp, $stop_timestamp, $graph );
		return $resultRange;
	}

	/**
	 * Get the number of available Test Results
	 *
	 * @return integer
	 */
	public function getTestResultNumber(){
		$instance  = $this->getInstance();
		$test_result_repository = tx_caretaker_TestResultRepository::getInstance();
		$resultNumber = $test_result_repository->getResultNumberByNode( $this );
		return $resultNumber;
	}

        /**
	 * Get the TestResultRange for the Offset and Limit
         *
	 * @see caretaker/trunk/classes/nodes/tx_caretaker_AbstractNode#getTestResultRange()
	 * @param $graph True by default. Used in the resultrange repository the specify the handling of the last result. For more information see tx_caretaker_testResultRepository.
	 */
	public function getTestResultRangeByOffset($offset=0, $limit=10){
		$instance  = $this->getInstance();
		$test_result_repository = tx_caretaker_TestResultRepository::getInstance();
		$resultRange = $test_result_repository->getResultRangeByNodeAndOffset( $this, $offset, $limit );
		return $resultRange;
	}
}
?>