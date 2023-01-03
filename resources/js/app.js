window.Vue = require('vue');

import vuetify from './vuetify'
import JsonExcel from "vue-json-excel";
 
Vue.component("downloadExcel", JsonExcel);

Vue.component(
    'report-table',
    require('./components/Table.vue').default
);

const app = new Vue({
    vuetify,
    el: '#app'
});
