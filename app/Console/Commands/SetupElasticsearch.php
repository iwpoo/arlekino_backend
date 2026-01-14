<?php

namespace App\Console\Commands;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Throwable;

class SetupElasticsearch extends Command
{
    protected $signature = 'app:setup-elasticsearch';
    protected $description = 'Настройка индекса Elasticsearch для поиска и рекомендаций';

    public function handle(): void
    {
        try {
            $client = ClientBuilder::create()
                ->setHosts([config('services.elasticsearch.host')])
                ->build();

            $indexName = 'search_index';

            if ($client->indices()->exists(['index' => $indexName])->asBool()) {
                $client->indices()->delete(['index' => $indexName]);
            }

            $client->indices()->create([
                'index' => $indexName,
                'body' => [
                    'settings' => [
                        'analysis' => [
                            'analyzer' => [
                                'russian_analyzer' => [
                                    'type' => 'russian',
                                ]
                            ]
                        ],
                        'number_of_shards' => 3,
                        'number_of_replicas' => 1,
                    ],
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'entity_type' => ['type' => 'keyword'],
                            'user_id' => ['type' => 'integer'],
                            'is_published' => ['type' => 'boolean'],

                            'title' => ['type' => 'text', 'analyzer' => 'russian_analyzer'],
                            'content' => ['type' => 'text', 'analyzer' => 'russian_analyzer'],
                            'name' => ['type' => 'text'],
                            'username' => ['type' => 'keyword'],

                            'price' => ['type' => 'float'],
                            'popularity' => ['type' => 'integer'],

                            'created_at' => [
                                'type' => 'date',
                                'format' => 'yyyy-mm-dd HH:mm:ss||yyyy-MM-dd\'T\'HH:mm:ss.SSSSSSZ||epoch_millis'
                            ],

                            'suggest_field' => [
                                'type' => 'completion'
                            ],

                            'category_id' => ['type' => 'integer'],
                        ]
                    ]
                ]
            ]);

            $this->info("Индекс $indexName успешно настроен с поддержкой даты и фильтров.");
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
    }
}
