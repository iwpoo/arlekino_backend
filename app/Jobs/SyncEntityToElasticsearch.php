<?php

namespace App\Jobs;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\AuthenticationException;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEntityToElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected Model $model,
        protected bool $isDelete = false
    ) {
        $this->onQueue('default');
    }

    /**
     * @throws AuthenticationException
     * @throws ClientResponseException
     * @throws ServerResponseException
     * @throws MissingParameterException
     */
    public function handle(): void
    {
        if (!$this->isDelete && !$this->model->exists) {
            return;
        }

        $client = ClientBuilder::create()
            ->setHosts([config('services.elasticsearch.host')])
            ->build();

        $index = config('services.elasticsearch.index', 'search_index');
        $id = $this->model->getTable() . '_' . $this->model->getKey();

        try {
            if ($this->isDelete) {
                $client->delete(['index' => $index, 'id' => $id]);
                return;
            }

            $data = $this->model->toSearchableArray();

            $data['entity_type'] = $this->model->getTable();
            $data['user_id'] = $this->model->user_id ?? $this->model->id;
            $data['popularity'] = $this->model->views_count ?? $this->model->followers_count ?? 0;

            $client->index([
                'index' => $index,
                'id' => $id,
                'body' => $data
            ]);
        } catch (Exception $e) {
            Log::error("Elasticsearch Sync Error for ID $id: " . $e->getMessage());
            throw $e;
        }
    }
}
