<?php

namespace Tapp\LaravelAwsSecretsManager\Tests;

use Aws\SecretsManager\SecretsManagerClient;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;
use Tapp\LaravelAwsSecretsManager\LaravelAwsSecretsManager;

/**
 * 
 */
class LaravelAwsSecretsManagerCacheAllTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.env', 'dev');
        $app['config']->set('aws-secrets-manager.enabled-environments', ['dev']);
        $app['config']->set('aws-secrets-manager.variables-config', []);
        $app['config']->set('aws-secrets-manager.cache-expiry', 1);
        $app['config']->set('aws-secrets-manager.cache-key-list', 'aws-all');
	}

    /**
     * @test
     */
    public function loadSecrets_sets_all_variables_in_cache()
    {
        $secretList = [
            'SecretList' => [
                [
                    'ARN' => 'test1',
                    'SecretString' => json_encode([
                        'name' => 'FIRST_VAR',
                        'value' => 'var1',
                    ]),
                ],
                [
                    'ARN' => 'test2',
                    'SecretString' => json_encode([
                        'name' => 'SECOND_VAR',
                        'value' => 'var2',
                    ]),
                ],
            ],
        ];

        $mock = Cache::shouldReceive('store')->andReturnSelf();
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('put')->with('FIRST_VAR', 'var1', 60);
        $mock->shouldReceive('put')->with('SECOND_VAR', 'var2', 60);
        $mock->shouldReceive('put')->with('aws-all', ['FIRST_VAR', 'SECOND_VAR'], 60);

        $secrestManager = $this->getTestableAwsSecretsManager($secretList);
        $secrestManager->loadSecrets();

        $this->assertEquals('var1', getenv('FIRST_VAR'));
        $this->assertEquals('var2', getenv('SECOND_VAR'));
    }

    /**
     * @test
     */
    public function loadSecrets_sets_all_variables_in_cache_when_using_array()
    {
        $secretList = [
            'SecretList' => [
                [
                    'ARN' => 'test1',
                    'SecretString' => json_encode([
                        'FIRST_VAR' => 'var1a',
                        'SECOND_VAR' => 'var2a',
                    ]),
                ],
            ],
        ];

        $mock = Cache::shouldReceive('store')->andReturnSelf();
        $mock->shouldReceive('get')->andReturn(null);
        $mock->shouldReceive('put')->with('FIRST_VAR', 'var1a', 60);
        $mock->shouldReceive('put')->with('SECOND_VAR', 'var2a', 60);
        $mock->shouldReceive('put')->with('aws-all', ['FIRST_VAR', 'SECOND_VAR'], 60);

        $secrestManager = $this->getTestableAwsSecretsManager($secretList);
        $secrestManager->loadSecrets();

        $this->assertEquals('var1a', getenv('FIRST_VAR'));
        $this->assertEquals('var2a', getenv('SECOND_VAR'));
    }

    /**
     * @test
     */
    public function loadSecrets_restores_all_variables_from_cache()
    {
        $mock = Cache::shouldReceive('store')->andReturnSelf();
        $mock->shouldReceive('get')->with('aws-all')->andReturn(['THIRD_VAR', 'FOURTH_VAR']);
        $mock->shouldReceive('get')->with('THIRD_VAR')->andReturn('var3');
        $mock->shouldReceive('get')->with('FOURTH_VAR')->andReturn('var4');

        $secrestManager = $this->getTestableAwsSecretsManager(['SecretList' => []]);
        $secrestManager->loadSecrets();

        $this->assertEquals('var3', getenv('THIRD_VAR'));
        $this->assertEquals('var4', getenv('FOURTH_VAR'));
    }

    /**
     * Extend the secrets manager so we can mock the AWS client
     */
    private function getTestableAwsSecretsManager($secretList = [])
    {
        $client = $this->getMockSecretsManagerClient($secretList);

        return new class ($client) extends LaravelAwsSecretsManager {
            protected $client;

            public function __construct($client)
            {
                $this->client = $client;
                parent::__construct();
            }

            protected function getSecretsManagerClient()
            {
                return $this->client;
            }
        };
    }

    private function getMockSecretsManagerClient($secretList = [])
    {
        return new class ($secretList) extends SecretsManagerClient {
            private $secretList = [];

            public function __construct($secretList)
            {
                $this->secretList = $secretList;
            }

            public function listSecrets($request)
            {
                return $this->secretList;
            }

            public function getSecretValue($request)
            {
                $id = $request['SecretId'];
                foreach ($this->secretList['SecretList'] as $key => $value) {
                    if ($value['ARN'] == $id) {
                        return $value;
                    }
                }
                return null;
            }
        };
    }
}
