<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportMarketTaxonomy extends Command
{
    protected $signature = 'import:market-taxonomy';
    protected $description = 'Import market taxonomy from JSON files into categories and questions tables';

    public function handle(): int
    {
        $this->info('Starting market taxonomy import...');

        $categoriesPath = storage_path('categories.json');
        $attributesPath = storage_path('attributes.json');
        $attributeValuesPath = storage_path('attribute_values.json');

        if (!File::exists($categoriesPath)) {
            $this->error("Categories file not found: $categoriesPath");
            return self::FAILURE;
        }

        if (!File::exists($attributesPath)) {
            $this->error("Attributes file not found: $attributesPath");
            return self::FAILURE;
        }

        if (!File::exists($attributeValuesPath)) {
            $this->error("Attribute values file not found: $attributeValuesPath");
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($categoriesPath, $attributesPath, $attributeValuesPath) {
                $this->info('Clearing existing categories and questions...');

                Question::query()->delete();
                Category::query()->delete();

                $categoriesJson = json_decode(File::get($categoriesPath), true);
                $attributesJson = json_decode(File::get($attributesPath), true);
                $attributeValuesJson = json_decode(File::get($attributeValuesPath), true);

                $attributesLookup = [];
                foreach ($attributesJson['attributes'] as $attribute) {
                    $attributesLookup[$attribute['id']] = $attribute;
                }

                $attributeValuesLookup = [];
                foreach ($attributeValuesJson['values'] as $value) {
                    $attributeValuesLookup[$value['id']] = $value;
                }

                $this->info('Importing categories...');
                $categoryLookup = [];
                $categoriesToProcess = [];

                foreach ($categoriesJson['verticals'] as $vertical) {
                    $this->flattenCategoryTree($vertical['categories'], $categoriesToProcess);
                }

                $bar = $this->output->createProgressBar(count($categoriesToProcess));
                $bar->start();

                usort($categoriesToProcess, function ($a, $b) {
                    $levelA = $a['level'] ?? 0;
                    $levelB = $b['level'] ?? 0;
                    return $levelA - $levelB;
                });

                foreach ($categoriesToProcess as $categoryData) {
                    $parentId = null;
                    if ($categoryData['parent_id']) {
                        $parentCategory = $categoryLookup[$categoryData['parent_id']] ?? null;
                        $parentId = $parentCategory ? $parentCategory->id : null;
                    }

                    $category = Category::create([
                        'name' => $categoryData['name'],
                        'parent_id' => $parentId,
                        'external_id' => $categoryData['id'],
                    ]);

                    $categoryLookup[$categoryData['id']] = $category;

                    if (isset($categoryData['attributes']) && is_array($categoryData['attributes'])) {
                        foreach ($categoryData['attributes'] as $attributeRef) {
                            $attributeId = $attributeRef['id'];
                            if (isset($attributesLookup[$attributeId])) {
                                $attribute = $attributesLookup[$attributeId];

                                $type = isset($attribute['values']) && count($attribute['values']) > 0 ? 'select' : 'text';

                                $options = [];
                                if ($type === 'select' && isset($attribute['values']) && is_array($attribute['values'])) {
                                    foreach ($attribute['values'] as $valueRef) {
                                        $valueId = $valueRef['id'];
                                        if (isset($attributeValuesLookup[$valueId])) {
                                            $options[] = $attributeValuesLookup[$valueId]['name'];
                                        }
                                    }
                                }

                                Question::create([
                                    'question' => $attribute['name'],
                                    'name' => $attribute['handle'] ?? $attribute['name'],
                                    'type' => $type,
                                    'options' => $options,
                                    'category_id' => $category->id,
                                ]);
                            }
                        }
                    }

                    $bar->advance();
                }

                $bar->finish();
                $this->newLine();
                $this->info('Categories and questions imported successfully!');
            });
        } catch (Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Market taxonomy import completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Recursively flatten category tree structure into a flat array
     */
    private function flattenCategoryTree(array $categories, array &$result, ?string $parentId = null): void
    {
        foreach ($categories as $categoryData) {
            $categoryData['parent_id'] = $parentId;

            $result[] = $categoryData;

            if (isset($categoryData['children']) && is_array($categoryData['children'])) {
                $this->flattenCategoryTree($categoryData['children'], $result, $categoryData['id']);
            }
        }
    }
}
