<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use Symfony\Component\HttpClient\HttpClient;

use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Bitrix24\SDK\Core\Credentials\AccessToken;
use Bitrix24\SDK\Core\Credentials\Credentials;
use Bitrix24\SDK\Core\Credentials\Scope;
use Bitrix24\SDK\Core\ApiClient;
use Bitrix24\SDK\Core\CoreBuilder;
use Bitrix24\SDK\Core\Batch;
use Bitrix24\User\User;


use Session;

class BitrixApi {    
    private $apiClient;
    private $core;
    private $batch;

    private function callBatch($method, $select, $filter = []) {
        $order = ['ID' => 'ASC'];
        $filter['>ID'] = 1;
        $result = $this->batch->getTraversableList($method, $order, $filter, $select);

        // Результатом вызова пакетного метода всегда является коллекция, можно обернуть
        return collect($result);
    }

    private function getResponse($method, $args = []) {
        $result = $this->apiClient->getResponse($method, $args)->getContent();
        $result = json_decode($result)->result;

        return $result;
    }

    public function __construct($params) {
        $appProfile = new ApplicationProfile(
            config("bitrix.APP_ID"), 
            config("bitrix.APP_SECRET_CODE"), 
            new Scope(config("bitrix.APP_SCOPE"))
        );
        
        $token = new AccessToken($params['AUTH_ID'], $params['REFRESH_ID'], 120);
        $domain = 'https://' . $params['DOMAIN'];
        
        $credentials = Credentials::createForOAuth($token, $appProfile, $domain);
        $client = HttpClient::create(['http_version' => '2.0']);
        $log = Log::channel('stack');
        
        $this->apiClient = new ApiClient($credentials, $client, $log);    

        $this->core = (new CoreBuilder())
        ->withLogger($log)
        ->withWebhookUrl('https://' . $params['DOMAIN'])
        ->withApiClient($this->apiClient)
        ->build();

        $this->batch = new Batch($this->core, $log); 
    }

    public function getUsers() {
        $select = ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME'];
        $secondName = $user['SECOND_NAME'] ?? '';

        $users = $this->callBatch('user.get', $select)->map(fn($user) => [
            'name' => "{$user['LAST_NAME']} {$user['NAME']} {$secondName}",
            'id' => $user['ID']
        ])->sortBy('name')->values()->all();

        return $users;
    }

    public function getCompanyFields() {
        $result = $this->getResponse('crm.company.fields');

        return collect($result);
    }

    public function getStatusValues($entityID) {
        $result = $this->getResponse('crm.status.entity.items', ['entityId' => $entityID]);

        return $result;
    }

    public function getCurrentUser() {
        $result = $this->getResponse('user.current');

        return $result;
    }

    public function getCompanies($userList, $minCommDate, $fields) {
        $userCompanies = [];

        foreach ($userList as $userId) {
            // Добавляем компании в конец массива поэлементно
            array_push($userCompanies, ...$this->getUserCompanies($userId, $minCommDate, $fields));
        }

        return $userCompanies;
    }

    public function getUserCompanies($userId, $minCommDate, $fields) {
        // Запрашиваем список компаний
        $select = ['ID'];
        array_push($select, ...$fields);

        $filter = ['ASSIGNED_BY_ID' => $userId];
        $companies =  $this->callBatch('crm.company.list', $select, $filter);

        $companyIDList = $companies->map(fn($company) => $company['ID']);

        if ($companyIDList->count() == 0) {
            return [];
        }
        
        // Запрашиваем список сделок по данным компаниям за данный период
        $select = ["ID", "COMPANY_ID"];
        $filter = [ 
            "COMPANY_ID" => $companyIDList->all(),
            ">DATE_CREATE" => $minCommDate
        ];    
        $deals = $this->callBatch('crm.deal.list', $select, $filter);

        // Отбрасываем компании, у которых есть такие сделки
        $dealCompanies = $deals->map(fn($deal) => $deal['COMPANY_ID'])->unique()->values();
        $companyIDList = $companyIDList->reject(fn($id) => $dealCompanies->contains($id));

        if ($companyIDList->count() == 0) {
            return [];
        }

        // Запрашиваем список дел по отфильтрованным компаниям за данный период
        $select = ["ID", "OWNER_ID"];
        $filter = [
            "OWNER_TYPE_ID" => 4, // Тип владельца дела - компания
            "OWNER_ID" => $companyIDList->all(),
            ">CREATED" => $minCommDate
        ];
        $activities = $this->callBatch('crm.activity.list', $select, $filter);

        // Отбрасываем компании, у которых есть такие дела
        $activityCompanies = $activities->map(fn($activity) => $activity['OWNER_ID'])->unique()->values();
        $companyIDList = $companyIDList->reject(fn($id) => $activityCompanies->contains($id));

        if ($companyIDList->count() == 0) {
            return [];
        }

        // Запрашиваем список контактов, связанных с отфильтрованными компаниями
        $select = ["ID", "COMPANY_ID"];
        $filter = ["COMPANY_ID" => $companyIDList->all()];
        $contacts = $this->callBatch('crm.contact.list', $select, $filter);

        // Создаем словарь контакт => компания
        $contactCompanies = $contacts->reduce(function($map, $contact) {
            $map->put($contact['ID'], $contact['COMPANY_ID']);

            return $map;
        }, collect([]));

        // Запрашиваем список дел, связанных с выбранными контактами за данный период
        $contactIDList = $contacts->map(fn($contact) => $contact['ID']);
        $select = ["ID", "OWNER_ID"];
        $filter = [
            "OWNER_TYPE_ID" => 3, // Тип владельца дела - контакт
            "OWNER_ID" => $contactIDList->all(),
            ">CREATED" => $minCommDate
        ];
        $contactActivities = $this->callBatch('crm.activity.list', $select, $filter);

        // Отбрасываем компании, у контактов которых есть такие дела
        $contactActivityCompanies = $contactActivities->map(fn($activity) => $contactCompanies[$activity['OWNER_ID']] ?? []);
        $companyIDList = $companyIDList->reject(fn($id) => $contactActivityCompanies->contains($id));

        if ($companyIDList->count() == 0) {
            return [];
        }

        // Запрашиваем список сделок по отфильтрованным компаниям
        $select = ["ID", "COMPANY_ID"];
        $filter = [ 
            "COMPANY_ID" => $companyIDList->all()
        ];    
        $deals = $this->callBatch('crm.deal.list', $select, $filter);

        // Создаем словарь сделка => компания
        $dealCompanies = $deals->reduce(function($map, $deal) {
            $map->put($deal['ID'], $deal['COMPANY_ID']);

            return $map;
        }, collect([]));

        // Запрашиваем список дел, связанных с выбранными сделками за данный период
        $dealIDList = $deals->map(fn($deal) => $deal['ID']);
        $select = ["ID", "OWNER_ID"];
        $filter = [
            "OWNER_TYPE_ID" => 2, // Тип владельца дела - сделка
            "OWNER_ID" => $dealIDList->all(),
            ">CREATED" => $minCommDate
        ];
        $dealActivities = $this->callBatch('crm.activity.list', $select, $filter);
        
        if ($dealCompanies->count() != 0) {
            $dealActivityCompanies = $dealActivities->map(fn($deal) => $dealCompanies[$deal['OWNER_ID']]);
            $companyIDList = $companyIDList->reject(fn($id) => $dealActivityCompanies->contains($id));
        }

        $companies = $companies->filter(fn($company) => $companyIDList->contains($company['ID']));

        return $companies->all();
    }
}