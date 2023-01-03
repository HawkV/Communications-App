<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="{{ url(mix('/css/app.css')) }}" rel="stylesheet">
        <title>Laravel Vuetify</title>
    </head>
    <body>
        <div id="app">
            <v-app>
                <report-table 
                    users="{{ json_encode($users) }}" 
                    company-fields="{{ json_encode($companyFields) }}"
                    column-settings="{{ json_encode($columnSettings) }}"
                    current-user="{{ $currentUser }}"
                    domain="{{ json_encode($domain) }}"
                    app-folder="{{ $appFolder }}"
                ></report-table>
            </v-app>
        </div>    
        <script src="//api.bitrix24.com/api/v1/"></script>
        <script src="{{ url(mix('/js/app.js')) }}"></script>
    </body>
</html>