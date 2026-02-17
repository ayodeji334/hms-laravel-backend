<?php

namespace App\Http\Controllers;

use App\Models\LabTestResultTemplate;
use App\Models\LabTestTemplateCategory;
use App\Models\LabTestTemplateTable;
use App\Models\LabTestTemplateTableColumn;
use App\Models\LabTestTemplateTableRow;
use App\Models\LabTestTemplateTableRowCategory;
use App\Models\LabTestTemplateTableRowCategoryRow;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class LabTestResultTemplateController extends Controller
{
    public function create(Request $request)
    {

        $request->validate([
            'name' => ['required', 'string', 'unique:lab_test_result_templates,name'],
            'is_save_to_draft' => ['required', 'boolean'],

            'input_fields' => ['nullable', 'array'],
            'input_fields.*.fieldName' => ['present', 'string', 'nullable'],
            'input_fields.*.value' => ['present', 'string', 'nullable'],

            'tables' => ['nullable', 'array'],
            'tables.*.columns' => ['present', 'array'],
            'tables.*.columns.*.header' => ['present', 'string', 'nullable'],
            'tables.*.columns.*.subColumns' => ['nullable', 'array'],
            'tables.*.columns.*.subColumns.*' => ['present', 'string', 'nullable'],

            'tables.*.rows' => ['present', 'array'],
            'tables.*.rows.*.values' => ['present', 'array'],
            'tables.*.rows.*.values.*' => ['present', 'string', 'nullable'],

            'tables.*.row_categories' => ['nullable', 'array'],
            'tables.*.row_categories.*.name' => ['present', 'string', 'nullable'],
            'tables.*.row_categories.*.rows' => ['present', 'array'],
            'tables.*.row_categories.*.rows.*.values' => ['present', 'array'],
            'tables.*.row_categories.*.rows.*.values.*' => ['present', 'string', 'nullable'],

            'categories' => ['nullable', 'array'],
            'categories.*.name' => ['present', 'string', 'nullable'],
            'categories.*.input_fields' => ['nullable', 'array'],
            'categories.*.input_fields.*.fieldName' => ['present', 'string', 'nullable'],
            'categories.*.input_fields.*.value' => ['present', 'string', 'nullable'],

            'categories.*.tables' => ['present', 'array'],
            'categories.*.tables.*.columns' => ['present', 'array'],
            'categories.*.tables.*.columns.*.header' => ['present', 'string', 'nullable'],
            'categories.*.tables.*.columns.*.subColumns' => ['nullable', 'array'],
            'categories.*.tables.*.columns.*.subColumns.*' => ['present', 'string', 'nullable'],

            'categories.*.tables.*.rows' => ['present', 'array'],
            'categories.*.tables.*.rows.*.values' => ['present', 'array'],
            'categories.*.tables.*.rows.*.values.*' => ['present', 'string', 'nullable'],

            'categories.*.tables.*.row_categories' => ['nullable', 'array'],
            'categories.*.tables.*.row_categories.*.name' => ['present', 'string', 'nullable'],
            'categories.*.tables.*.row_categories.*.rows' => ['present', 'array'],
            'categories.*.tables.*.row_categories.*.rows.*.values' => ['present', 'array'],
            'categories.*.tables.*.row_categories.*.rows.*.values.*' => ['present', 'string', 'nullable'],
        ]);

        try {
            DB::beginTransaction();

            $template = new LabTestResultTemplate();
            $template->name = $request['name'];
            $template->added_by_id = Auth::id();
            $template->last_updated_by_id = Auth::id();
            $template->input_fields = $request['input_fields'] ?? [];
            $template->save();

            // Handle plain tables
            foreach ($request['tables'] ?? [] as $tableData) {
                $table = new LabTestTemplateTable();
                $table->template_id = $template->id;
                $table->save();

                foreach ($tableData['rows'] ?? [] as $index => $row) {
                    LabTestTemplateTableRow::create([
                        'table_id' => $table->id,
                        'values' => $row['values'],
                        'index' => $index,
                    ]);
                }

                foreach ($tableData['columns'] ?? [] as $index => $column) {
                    LabTestTemplateTableColumn::create([
                        'table_id' => $table->id,
                        'header' => $column['header'],
                        'sub_columns' => $column['subColumns'],
                        'index' => $index,
                    ]);
                }

                foreach ($tableData['row_categories'] ?? [] as $catIndex => $rowCat) {
                    $category = new LabTestTemplateTableRowCategory([
                        'table_id' => $table->id,
                        'name' => $rowCat['name'],
                        'index' => $catIndex,
                    ]);
                    $category->save();

                    foreach ($rowCat['rows'] ?? [] as $rowIndex => $row) {
                        LabTestTemplateTableRowCategoryRow::create([
                            'category_id' => $category->id,
                            'values' => $row['values'],
                            'index' => $rowIndex,
                        ]);
                    }
                }
            }

            // Handle categories
            foreach ($request['categories'] ?? [] as $catData) {
                $category = new LabTestTemplateCategory([
                    'name' => $catData['name'],
                    'input_fields' => $catData['input_fields'] ?? [],
                    'template_id' => $template->id
                ]);
                $category->save();

                // $template->categories()->attach($category->id);

                foreach ($catData['tables'] ?? [] as $tableData) {
                    $table = new LabTestTemplateTable();
                    $table->category_id = $category->id;
                    $table->save();

                    foreach ($tableData['rows'] ?? [] as $index => $row) {
                        LabTestTemplateTableRow::create([
                            'table_id' => $table->id,
                            'values' => $row['values'] ?? "",
                            'index' => $index,
                        ]);
                    }

                    foreach ($tableData['columns'] ?? [] as $index => $column) {
                        LabTestTemplateTableColumn::create([
                            'table_id' => $table->id,
                            'header' => $column['header'] ?? "",
                            'sub_columns' => $column['subColumns'] ?? '',
                            'index' => $index,
                        ]);
                    }

                    foreach ($tableData['row_categories'] ?? [] as $catIndex => $rowCat) {
                        $categoryRow = new LabTestTemplateTableRowCategory([
                            'table_id' => $table->id,
                            'name' => $rowCat['name'],
                            'index' => $catIndex,
                        ]);
                        $categoryRow->save();

                        foreach ($rowCat['rows'] ?? [] as $rowIndex => $row) {
                            LabTestTemplateTableRowCategoryRow::create([
                                'category_id' => $categoryRow->id,
                                'values' => $row['values'],
                                'index' => $rowIndex,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Template created successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Template creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $q = $request->get('q', '');

            $templates = LabTestResultTemplate::with([
                'addedBy:id,firstname,lastname',
                'lastUpdatedBy:id,firstname,lastname',
                'tables.rows',
                'tables.columns',
                'tables.rowCategories.rows',
                'categories.tables.rows',
                'categories.tables.columns',
                'categories.tables.rowCategories.rows'
            ])->orderBy('created_at', 'desc')->when($q, function ($query, $q) {
                $query->where('name', 'LIKE', "%{$q}%");
            })->paginate(50);

            return response()->json([
                'message' => 'Templates fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $templates,
            ]);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $template = LabTestResultTemplate::with([
                'tables.rows',
                'tables.columns',
                'categories.tables.rows',
                'categories.tables.columns',
                'addedBy',
                'lastUpdatedBy',
            ])->findOrFail($id);

            return response()->json([
                'message' => 'Template fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $template,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Template not found',
                'success' => false,
                'status' => 'error',
            ], 400);
        } catch (exception $e) {
            Log::error($e);
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $template = LabTestResultTemplate::findOrFail($id);
            $template->delete();

            return response()->json([
                'message' => 'Template deleted successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Template not found',
                'status' => 'error',
                'success' => false,
            ], 400);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function update(string $id, Request $request)
    {
        DB::beginTransaction();
        try {
            $existing = LabTestResultTemplate::where('name', $request->name)
                ->where('id', '!=', $id)
                ->exists();

            if ($existing) {
                return response()->json([
                    'message' => 'Template name already exist',
                    'status' => 'error',
                    'success' => true,
                ], 400);
            }

            $template = LabTestResultTemplate::with(['tables', 'categories.tables'])->findOrFail($id);

            // $template->update([
            //     'name' => $request->name,
            //     'input_fields' => $request->input_fields,
            //     'last_updated_by_id' => Auth::id(),
            // ]);
            $template->name = $request->name;
            $template->input_fields = $request->input_fields;
            $template->last_updated_by_id = Auth::id();
            $template->save();

            // Delete previous related records
            LabTestTemplateTable::where('template_id', $template->id)->delete();
            LabTestTemplateCategory::where('template_id', $template->id)->delete();

            // Handle plain tables
            foreach ($request['tables'] ?? [] as $tableData) {
                $table = new LabTestTemplateTable();
                $table->template_id = $template->id;
                $table->index = $tableData['index'] ?? null;
                $table->save();

                foreach ($tableData['rows'] ?? [] as $index => $row) {
                    LabTestTemplateTableRow::create([
                        'table_id' => $table->id,
                        'values' => $row['values'] ?? "",
                        'index' => $index,
                    ]);
                }

                foreach ($tableData['columns'] ?? [] as $index => $column) {
                    LabTestTemplateTableColumn::create([
                        'table_id' => $table->id,
                        'header' => $column['header'] ?? '',
                        'sub_columns' => $column['sub_columns'],
                        'index' => $index,
                    ]);
                }

                foreach ($tableData['row_categories'] ?? [] as $catIndex => $rowCat) {
                    $category = new LabTestTemplateTableRowCategory([
                        'table_id' => $table->id,
                        'name' => $rowCat['name'],
                        'index' => $catIndex,
                    ]);
                    $category->save();

                    foreach ($rowCat['rows'] ?? [] as $rowIndex => $row) {
                        LabTestTemplateTableRowCategoryRow::create([
                            'category_id' => $category->id,
                            'values' => $row['values'],
                            'index' => $rowIndex,
                        ]);
                    }
                }
            }

            // Handle categories
            foreach ($request['categories'] ?? [] as $catIndex => $catData) {
                $category = new LabTestTemplateCategory([
                    'name' => $catData['name'],
                    'input_fields' => $catData['input_fields'] ?? [],
                    'template_id' => $template->id,
                    'index' => $catIndex,
                ]);
                $category->save();

                foreach ($catData['tables'] ?? [] as $tableData) {
                    $table = new LabTestTemplateTable();
                    $table->category_id = $category->id;
                    $table->index = $tableData['index'] ?? null;
                    $table->save();

                    foreach ($tableData['rows'] ?? [] as $index => $row) {
                        LabTestTemplateTableRow::create([
                            'table_id' => $table->id,
                            'values' => $row['values'] ?? "",
                            'index' => $index,
                        ]);
                    }

                    foreach ($tableData['columns'] ?? [] as $index => $column) {
                        LabTestTemplateTableColumn::create([
                            'table_id' => $table->id,
                            'header' => $column['header'] ?? "",
                            'sub_columns' => $column['subColumns'] ?? '',
                            'index' => $index,
                        ]);
                    }

                    foreach ($tableData['row_categories'] ?? [] as $catIndex => $rowCat) {
                        $categoryRow = new LabTestTemplateTableRowCategory([
                            'table_id' => $table->id,
                            'name' => $rowCat['name'],
                            'index' => $catIndex,
                        ]);
                        $categoryRow->save();

                        foreach ($rowCat['rows'] ?? [] as $rowIndex => $row) {
                            LabTestTemplateTableRowCategoryRow::create([
                                'category_id' => $categoryRow->id,
                                'values' => $row['values'],
                                'index' => $rowIndex,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Template updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Template not found',
                'status' => 'error',
                'success' => false,
            ], 400);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
}
