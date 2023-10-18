<?php declare(strict_types=1);

/*
 Copyright (c) 2023, Manticore Software LTD (https://manticoresearch.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License version 2 or any later
 version. You should have received a copy of the GPL license along with this
 program; if you did not, you can find it at http://www.gnu.org/
 */

use Ds\Vector;
use Manticoresearch\Buddy\Core\ManticoreSearch\Client as HTTPClient;
use Manticoresearch\Buddy\Core\ManticoreSearch\Endpoint as ManticoreEndpoint;
use Manticoresearch\Buddy\Core\ManticoreSearch\RequestFormat;
use Manticoresearch\Buddy\Core\ManticoreSearch\Response as ManticoreResponse;
use Manticoresearch\Buddy\Core\ManticoreSearch\Settings as ManticoreSettings;
use Manticoresearch\Buddy\Core\Network\Request;
use Manticoresearch\Buddy\Core\Network\Response;
use Manticoresearch\Buddy\CoreTest\Trait\TestHTTPServerTrait;
use Manticoresearch\Buddy\CoreTest\Trait\TestInEnvironmentTrait;
use Manticoresearch\Buddy\Plugin\Insert\Handler;
use Manticoresearch\Buddy\Plugin\Insert\Payload;
use PHPUnit\Framework\TestCase;

class InsertQueryHandlerTest extends TestCase {

	use TestHTTPServerTrait;
	use TestInEnvironmentTrait;

	protected function tearDown(): void {
		self::finishMockManticoreServer();
	}

	/**
	 * @param Request $networkRequest
	 * @param string $serverUrl
	 * @param string $resp
	 */
	protected function runTask(Request $networkRequest, string $serverUrl, string $resp): void {
		$payload = Payload::fromRequest($networkRequest);
		/** @var Vector<array{key:string,value:mixed}> */
		$vector = new Vector(
			[
			['key' => 'configuration_file', 'value' => '/etc/manticoresearch/manticore.conf'],
			['key' => 'worker_pid', 'value' => 7718],
			['key' => 'searchd.auto_schema', 'value' => '1'],
			['key' => 'searchd.listen', 'value' => '0.0.0:9308:http'],
			['key' => 'searchd.log', 'value' => '/var/log/manticore/searchd.log'],
			['key' => 'searchd.query_log', 'value' => '/var/log/manticore/query.log'],
			['key' => 'searchd.pid_file', 'value' => '/var/run/manticore/searchd.pid'],
			['key' => 'searchd.data_dir', 'value' => '/var/lib/manticore'],
			['key' => 'searchd.query_log_format', 'value' => 'sphinxql'],
			['searchd.buddy_path', 'value' => 'manticore-executor /workdir/src/ main.php --debug'],
			['key' => 'common.plugin_dir', 'value' => '/usr/local/lib/manticore'],
			['key' => 'common.lemmatizer_base', 'value' => '/usr/share/manticore/morph/'],
			]
		);
		$payload->setSettings(
			ManticoreSettings::fromVector($vector)
		);

		self::setBuddyVersion();
		$manticoreClient = new HTTPClient(new ManticoreResponse(), $serverUrl);
		$handler = new Handler($payload);
		$handler->setManticoreClient($manticoreClient);
		ob_flush();
		$task = $handler->run();
		$task->wait();

		$this->assertEquals(true, $task->isSucceed());
		/** @var Response */
		$result = $task->getResult()->getStruct();
		$this->assertEquals($resp, json_encode($result));
	}

	public function testInsertQueryExecutesProperly(): void {
		echo "\nTesting the execution of a task with INSERT query request\n";
		$resp = '[{"total":1,"error":"","warning":""}]';
		$mockServerUrl = self::setUpMockManticoreServer(false);
		$request = Request::fromArray(
			[
				'version' => 1,
				'error' => "table 'test' absent, or does not support INSERT",
				'payload' => 'INSERT INTO test(col1) VALUES(1)',
				'format' => RequestFormat::SQL,
				'endpointBundle' => ManticoreEndpoint::Sql,
				'path' => 'sql?mode=raw',
			]
		);
		$this->runTask($request, $mockServerUrl, $resp);
	}
}
