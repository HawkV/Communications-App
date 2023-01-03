<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\BitrixApi;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Models\Domain;
use App\Models\User;
use App\Models\Column;
use App\Models\ColumnSetting;

class BitrixController extends Controller
{
    private function settingData(ColumnSetting $setting) {
        $data = array_merge($setting->toArray(), $setting->column->toArray());

        $keyRemapping = [
            'title' => 'value',
            'label' => 'text' 
        ];

        foreach($keyRemapping as $old => $new) {
            $data[$new] = $data[$old];

            unset($data[$old]);
        }
        
        return $data;
    }

    /**
     * Показываем пустую таблицу со списком пользователей данного портала.
     * Здесь дополнительно происходит обработка служебных данных; вынести в BitrixApi/middleware?
     */
    public function show(Request $request)
    {
        $requestParams = $request->get('params');
        $B24App = new BitrixApi($requestParams);

        $domain = Domain::firstOrCreate([
            'domain' => $requestParams['DOMAIN']
        ]);

        $user = User::firstOrCreate([
            'bitrix_id' => $B24App->getCurrentUser()->ID,
            'domain_id' => $domain->id
        ]);

        $companyFields = $B24App->getCompanyFields();

        $columnSettings = $user->columnSettings;

        if ($columnSettings->isEmpty()) {
            $columnSettings = $domain->columnSettings;
        }

        /* #region Проверка данных в базе, запись при отсутствии; */

        // Добавляем системные поля, если в таблице их еще нет
        $systemColumns = Column::whereNull('domain_id')->get()
                               ->map(fn($value) => $value->title);

        $missingColumns = $companyFields->reject(fn($value, $key) => str_starts_with($key, 'UF_CRM_'))
                                        ->reject(fn($value, $key) => $systemColumns->contains($key));
        
        foreach ($missingColumns as $title => $field) {
            $column = new Column;

            $column->title = $title;
            $column->label = $field->title;

            $column->save();
        }

        // Если настройки не были найдены 
        if ($columnSettings->isEmpty()) { 
            $defaultSettings = ['TITLE', 'ASSIGNED_BY_ID'];
            $columns = Column::whereIn('title', $defaultSettings)->get();

            foreach($columns as $column) {
                $columnSetting = new ColumnSetting;

                $columnSetting->column_id = $column->id;
                $columnSetting->domain_id = $domain->id;
                $columnSetting->displayed = true;

                $columnSetting->save();
            }

            $columnSettings = $domain->columnSettings;
        }

        /* #endregion */

        $companyFields = $companyFields->map(function($item, $key) {
            return [
                'title' => $key,
                'label' => $item->listLabel?? $item->title
            ];
        })->values();

        $columnSettings = $columnSettings->map(fn($setting, $key) => $this->settingData($setting));

        $appFolder = Str::after(public_path(), config('bitrix.default_dir_path'));

        return view('welcome', [
            'users' => $B24App->getUsers(),
            'companyFields' => $companyFields->all(),
            'columnSettings' => $columnSettings->all(),
            'currentUser' => $user->id,
            'domain' => $domain,
            'appFolder' => $appFolder
        ]);
    }

    /**
     * Возвращаем компании данного пользователя
     */
    public function getCompanies(Request $request)
    {
        $api = new BitrixApi($request->get('params'));

        return $api->getCompanies(
            $request->get('userList'), 
            $request->get('minCommDate'),
            $request->get('fields')
        );
    }

    /**
     * Обновляем настройки столбцов данного пользователя
     * (отображение, сортировка, группировка)
     */
    function updateHeaderSettings(Request $request) {
        $headers = collect($request->get('headers')); 
        $user_id = $request->get('user_id');
        
        $headers->each(function($header) use ($user_id) {
            ColumnSetting::updateOrCreate(
                [
                    'user_id' => $user_id,
                    'column_id' => $header['column_id']
                ],
                [   
                    'displayed' => $header['displayed'],
                    'sort_order' => $header['sort_order'],
                    'sort_desc' => $header['sort_desc'],
                    'grouped' => $header['grouped']
                ]
            );
        }); 
    }   
    
    /**
     * Добавляем новый столбец в таблицу с результатами запросов
     * По умолчанию невидимый
     */
    function addHeader(Request $request) {
        $header = $request->get('header');
        $domain_id = $request->get('domain_id'); 
        $user_id = $request->get('user_id');

        if (!str_starts_with($header['title'], 'UF_CRM_')) {
            $domain_id = null;
        }

        $column = Column::updateOrCreate(
            [
                'title' => $header['title'],
                'domain_id' => $domain_id
            ],
            [
                'label' => $header['label']
            ]
        ); 

        $setting = ColumnSetting::updateOrCreate(
            [
                'user_id' => $user_id,
                'column_id'=> $column->id
            ]
        );

        return $this->settingData($setting);
    }
}