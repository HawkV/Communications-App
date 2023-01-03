import 'vuetify/dist/vuetify.min.css'

import Vue from 'vue'	
import Vuetify from 'vuetify'
import axios from 'axios'

Vue.prototype.$http = axios;

Vue.use(Vuetify)

import ru from 'vuetify/lib/locale/ru'

const opts = {
    lang: {
        locales: {ru},
        current: 'ru'
    }
}

export default new Vuetify(opts)