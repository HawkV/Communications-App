<template>
  <v-container fluid>
    <v-row>
      <!-- Выбор пользователя -->
      <v-col cols="12" sm="6" md="6">
        <v-autocomplete
          :items="userOptions"
          item-text="name"
          item-value="id"
          label="Ответственный"
          v-model="userList"
          multiple
        >
          <template v-slot:selection="{ item, index }">
            <span v-if="index < shownEmployees" class="black--text">
              <span v-if="index > 0">, </span>
              {{ item.name }}
            </span>
            <template v-if="index === shownEmployees">
              &nbsp;
              <span class="grey--text caption">
                (+{{ userList.length - shownEmployees }} сотрудников)
              </span>
            </template>
          </template>
        </v-autocomplete>
      </v-col>
      <!-- Выбор даты коммуникации -->
      <v-col cols="12" sm="6" md="4">
        <v-menu
          ref="menu"
          v-model="menu"
          :close-on-content-click="false"
          :return-value.sync="date"
          transition="scale-transition"
          offset-y
          min-width="auto"
        >
          <!-- computedDateFormatted -->
          <template v-slot:activator="{ on, attrs }">
            <v-text-field
              v-model="date"
              label="Дата последней коммуникации"
              prepend-icon="mdi-calendar"
              readonly
              v-bind="attrs"
              v-on="on"
            ></v-text-field>
          </template>
          <v-date-picker
            v-model="date"
            no-title
            scrollable
            show-adjacent-months
            :first-day-of-week="1"
            color="blue"
            :max="new Date().toISOString().substr(0, 10)"
          >
            <v-spacer></v-spacer>
            <v-btn text color="primary" @click="menu = false"> Отменить </v-btn>
            <v-btn text color="primary" @click="$refs.menu.save(date)"> OK </v-btn>
          </v-date-picker>
        </v-menu>
      </v-col>

      <v-col cols="12" sm="6" md="2">
        <v-btn block @click="checkCompanies" color="primary"> Поиск </v-btn>
      </v-col>
    </v-row>
    <!-- Таблица с данными -->
    <v-card>
      <v-card-title>
        <!-- Окно настроек таблицы -->
        <v-dialog v-model="dialog" width="600" hide-overlay @click:outside="closeDialog">
          <!-- Кнопка, открывающая настройки -->
          <template v-slot:activator="{ on, attrs }">
            <v-btn v-bind="attrs" v-on="on" icon>
              <v-icon> mdi-cog </v-icon>
            </v-btn>
          </template>

          <!-- Окно -->
          <v-card class="mx-auto">
            <v-card-title class="headline"> Настройки </v-card-title>
            <v-card-text>
              <!-- Список отображаемых столбцов -->
              <v-container class="pa-1">
                <v-item-group>
                  <v-row>
                    <v-col v-for="(header, i) in headers" :key="i" cols="12" md="4">
                        <v-item>
                          <div class = "inline-items">
                              <v-checkbox
                                v-model="tempSettings"
                                :label="header.text"
                                :value="header.value"
                                hide-details
                                
                                class="ma-0 pa-0"
                              >
                              </v-checkbox>
                              <v-btn color="error" size="20" icon @click="removeColumn(header)" class="ml-1">
                                <v-icon>mdi-close</v-icon>
                              </v-btn>
                          </div>
                        </v-item>
                    </v-col>
                  </v-row>
                </v-item-group>
                <!-- Добавление нового столбца в список -->
                <v-row>
                  <v-col cols="12" md="8" align-self="center">
                    <v-autocomplete
                      :items="columnOptions"
                      item-text="label"
                      item-value="title"
                      label="Дополнительные поля"
                      v-model="selectedHeader"
                      :search-input.sync="optionInput"
                    >
                      >
                    </v-autocomplete>
                  </v-col>
                  <v-col cols="12" md="4" align-self="center">
                    <v-btn color="primary" @click="addColumn(true)"> Добавить </v-btn>
                  </v-col>
                </v-row>
              </v-container>
            </v-card-text>
            <v-card-actions>
              <download-excel :data="excelCompanies">
                <v-btn color="blue darken-1" text> Скачать данные </v-btn>
              </download-excel>
              <v-spacer></v-spacer>
              <v-btn color="red darken-1" text @click="cancelSettings"> Отменить </v-btn>
              <v-btn color="green darken-1" text @click="acceptSettings"> Применить </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
        Компании
        <v-spacer></v-spacer>
        <!-- Поле поиска -->
        <v-text-field
          v-model="search"
          append-icon="mdi-magnify"
          label="Поиск"
          single-line
          hide-details
        >
        </v-text-field>
      </v-card-title>
      <!-- Таблица -->
      <v-data-table
        :headers="filteredHeaders"
        :items="companies"
        item-key="ID"
        class="elevation-1"
        :search="search"
        :custom-filter="filterAndRefresh"
        show-group-by
        :group-by.sync="groupBy"
        @update:group-by="onGroupBy"
        :loading="loading"
        :sort-by.sync="sortBy"
        :sort-desc.sync="sortDesc"
        loading-text="Данные загружаются..."
        :footer-props="{ 'items-per-page-options': [10, 20, 50, 100] }"
        hide-default-header
        fixed-header
        height="100%"
        multi-sort
      >
        <!-- Шапка таблицы -->
        <template v-slot:header="props">
          <thead>
            <tr>
              <template v-for="header in props.props.headers">
                <th :class="header.class" :key="header.value">
                  <span>{{ header.text }}</span>
                  <v-tooltip v-if="header.groupable" transition="none" bottom>
                    <template v-slot:activator="{ on, attrs }">
                      <v-btn
                        v-bind="attrs"
                        v-on="on"
                        @click.stop="props.on.group(header.value)"
                        icon
                      >
                        <v-icon>mdi-tray-full</v-icon>
                      </v-btn>
                    </template>
                    <span>Группировать</span>
                  </v-tooltip>
                  <v-tooltip v-if="header.sortable" transition="none" bottom>
                    <template v-slot:activator="{ on, attrs }">
                      <v-btn
                        v-bind="attrs"
                        v-on="on"
                        @click.stop="toggleOrder(header)"
                        icon
                      >
                        <template>
                          <v-icon :color="getColor(header)" v-if="header.sort_desc"
                            >mdi-sort-alphabetical-descending</v-icon
                          >
                          <v-icon :color="getColor(header)" v-else
                            >mdi-sort-alphabetical-ascending</v-icon
                          >
                        </template>
                      </v-btn>
                    </template>
                    <span>Сортировать</span>
                  </v-tooltip>
                  <v-chip v-if="header.sortable && header.sort_order > -1">
                    {{ header.sort_order + 1 }}
                  </v-chip>
                </th>
              </template>
            </tr>
          </thead>
        </template>

        <!-- Шапка группы -->
        <template v-slot:group.header="{ items, isOpen, toggle, remove, group }">
          <th class="sticky-group-header" colspan="100%">
            <v-icon
              @click="
                toggle();
                toggleItems(isOpen, group);
              "
            >
              {{ isOpen ? "mdi-minus" : "mdi-plus" }}
            </v-icon>
            {{ group }}: {{ groupCount[group] }}
            <v-icon @click="remove"> mdi-close </v-icon>
          </th>
        </template>

        <!-- Ссылка в ячейке -->
        <template v-slot:item.TITLE="{ item }">
          <v-input hide-details="auto"> <!-- :messages="['Пример дополнительной информации']"> -->
            <a
              :href="`https://${domainInfo.domain}/crm/company/details/${item.ID}/`"
              target="_blank"
              >{{ item.TITLE }}</a
            >
          </v-input>
        </template>
      </v-data-table>
    </v-card>
  </v-container>
</template>

<script>
export default {
  data: (vm) => ({
    search: "",
    companies: [],
    headers: [],
    loading: false,
    groupedItems: [],

    selectedColor: "blue",
    dialog: false,
    groupBy: null,
    oldGroupBy: null,

    displayed_items: {},

    userOptions: [],
    totalColumnOptions: [],
    columnOptions: [],
    optionInput: null,
    selectedHeader: null,
    userList: null,
    shownEmployees: 2,
    tempSettings: [],

    menu: false,

    classNames: {
      127: "A-класс",
      126: "B-класс",
      125: "C-класс",
      124: "D-класс",
      null: "Не классифицированы",
    },
    userMap: {},

    rowDialog: false,
  }),
  computed: {
    filteredHeaders() {
      return this.headers
        .filter((header) => header.displayed)
        .map((header) => {
          let newHeader = {
            text: header.text,
            value: header.value,
            class: header.class,
            sortable: header.sortable,
            groupable: header.groupable,
            sort_order: header.sort_order,
            options: {
              user_id: header.user_id,
            },
          };

          if (header.filter) {
            newHeader.filter = header.filter;
          }

          return newHeader;
        });
    },
    excelCompanies() {
      let data = this.companies.map((company) => {
        let item = {};

        this.filteredHeaders.map((header) => {
          item[header.text] = company[header.value];
        });

        return item;
      });


      console.log();
      console.log(data);

      return data;
    },
    sortedHeaders() {
      return [...this.headers]
        .filter((header) => header.sort_order > -1)
        .sort((header) => header.sort_order);
    },
    groupCount() {
      let groupValues = this.companies.map((company) => company[this.groupBy[0]]);

      let groupCount = groupValues.reduce((counts, item) => {
        counts[item] = (counts[item] || 0) + 1;

        return counts;
      }, {});

      return groupCount;
    },
    computedDateFormatted() {
      return this.formatDate(this.date);
    },
  },
  watch: {
    date(val) {
      this.dateFormatted = this.formatDate(this.date);
    },
  },
  created() {
    this.userOptions = JSON.parse(this.users);
    this.userOptions.forEach((user) => (this.userMap[user.id] = user.name));

    this.headers = JSON.parse(this.columnSettings);

    // Параметры, общие для всех колонок
    this.headers.forEach((header) => {
      header.class = "sticky-header title lighten-3 white";
      header.align = "left";
    });

    let groupHeader = this.headers.find((header) => header.grouped);

    if (groupHeader) {
      this.groupBy = [groupHeader.value];
    }

    this.sortBy = this.sortedHeaders.map((header) => header.value);
    this.sortDesc = this.sortedHeaders.map((header) => header.sort_desc);

    // Какие колонки отображаются как видимые в окне настроек
    this.tempSettings = [...this.filteredHeaders.map((header) => header.value)];

    console.log(this.tempSettings);

    // Колонки, которые мы можем добавить в список (исключим те, что уже добавлены)
    this.totalColumnOptions = JSON.parse(this.companyFields); 

    let headerTitles = this.headers.map((header) => header.value);
    this.columnOptions = this.totalColumnOptions.filter(
      (option) => !headerTitles.includes(option.title)
    );

    let minCommDate = new Date();
    minCommDate.setDate(minCommDate.getDate() - 30);
    minCommDate = minCommDate.toISOString().substr(0, 10);

    this.date = minCommDate;
    this.dateFormatted = this.formatDate(minCommDate);

    this.domainInfo = JSON.parse(this.domain);
  },
  methods: {
    toggleOrder(header) {
      if (header.sort_order == -1) {
        header.sort_order = this.sortedHeaders.length;

        this.sortBy.push(header.value);
        this.sortDesc.push(header.sort_desc);
      } else if (header.sort_desc) {
        this.removeFromSort(header);
      } else {
        header.sort_desc = !header.sort_desc;

        this.$set(this.sortDesc, header.sort_order, header.sort_desc);
      }
    },
    removeFromSort(header) {
      header.sort_desc = false;

      this.headers.forEach((other) => {
        if (other.sort_order > header.sort_order) {
          other.sort_order--;
        }
      });

      this.sortBy.splice(header.sort_order, 1);
      this.sortDesc.splice(header.sort_order, 1);

      header.sort_order = -1;
    },
    checkCompanies() {
      this.loading = true;
      this.companies = [];

      this.$http
        .post(`data`, {
          userList: this.userList,
          minCommDate: this.date,
          fields: this.headers.map((header) => header.value),
        })
        .then((response) => {
          console.log(response);

          this.companies = Object.values(response.data);
          console.log("read data");
          console.log(this.companies);
          
          this.companies.forEach((company) => {
            if (company.UF_CRM_FIELD_ABC) {
              company.UF_CRM_FIELD_ABC = this.classNames[company.UF_CRM_FIELD_ABC];
            }
            
            company.ASSIGNED_BY_ID = this.userMap[company.ASSIGNED_BY_ID];
          });

          this.loading = false;

          console.log("processed data");
          console.log(this.companies);

          this.$nextTick(function () {
            BX24.fitWindow();
          });
        });
    },
    getColor(header) {
      if (header.sort_order > -1) return "blue";

      return "gray";
    },
    formatDate(date) {
      if (!date) return null;

      const [year, month, day] = date.split("-");
      return `${day}/${month}/${year}`;
    },
    acceptSettings() {
      console.log(this.tempSettings);

      this.headers.forEach((header) => {
        console.log(header.value);
        header.displayed = this.tempSettings.includes(header.value);

        if (!header.displayed) {
          if (header.sort_order != -1) {
            this.removeFromSort(header);
          }

          if (this.groupBy && header.value == this.groupBy[0]) {
            this.groupBy = null;
          }
        }
      });

      this.dialog = false;
      this.saveSettings();
    },
    saveSettings() {
      let tempHeaders = [...this.headers];

      if (this.groupBy && this.groupBy[0]) {
        tempHeaders.find((header) => header.value == this.groupBy[0]).grouped = true;
      }

      this.$http.post(`settings`, {
        headers: tempHeaders,
        user_id: this.currentUser,
      });
    },
    cancelSettings() {
      this.tempSettings = [...this.filteredHeaders.map((header) => header.value)];
      this.dialog = false;
    },
    closeDialog() {
      this.tempSettings = [...this.filteredHeaders.map((header) => header.value)];
    },
    addColumn(local) {

      let optionIndex = this.columnOptions.findIndex(
        (option) => option.title == this.selectedHeader
      );

      if (optionIndex == -1) {
        return;
      }

      let newHeader = this.columnOptions[optionIndex];

      this.$http
        .post(`add_header`, {
          header: newHeader,
          domain_id: local ? this.domainInfo.id : null,
          user_id: this.currentUser,
        })
        .then((response) => {
          let header = response.data;

          // repeating code
          header.class = "sticky-header title lighten-3 white";
          header.align = "left";

          this.headers.push(header);

          this.optionInput = null;
  
          // TODO: вынести в updateColumnOptions
          let headerTitles = this.headers.map((header) => header.value);
          this.columnOptions = this.totalColumnOptions.filter(
            (option) => !headerTitles.includes(option.title)
          );
        });
    },
    removeColumn(header) {
      this.$http
        .post(`remove_header`, {
          header_id: header.id,
        })
        .then((response) => {
          this.headers = this.headers.filter(
            (item) => item.id != header.id
          );

          // TODO: вынести в updateColumnOptions
          let headerTitles = this.headers.map((header) => header.value);
          this.columnOptions = this.totalColumnOptions.filter(
            (option) => !headerTitles.includes(option.title)
          );

          // TODO: вынести в updateTempSettings
          this.tempSettings = [...this.filteredHeaders.map((header) => header.value)];
        });
    },
    onGroupBy(arg) {
      this.displayed_items = {};
      this.groupedItems = [];

      if (arg && typeof arg == "object") {
        arg = arg[0];
      }

      if (this.oldGroupBy) {
        // TODO: remove redundant code
        let headerIndex = this.headers.findIndex(
          (header) => header.value == this.oldGroupBy
        );
        let header = this.headers[headerIndex];

        this.$delete(this.headers[headerIndex], "filter");
      }

      this.oldGroupBy = arg;

      if (arg) {
        let headerIndex = this.headers.findIndex((header) => header.value == arg);
        let header = this.headers[headerIndex];

        this.$set(this.headers[headerIndex], "filter", (value) => {
          if (!this.displayed_items[value] || this.displayed_items[value] == 0) {
            this.displayed_items[value] = 1;
            return true;
          }

          if (!this.groupedItems) return true;

          return !this.groupedItems.includes(value);
        });

        this.headers[headerIndex] = header;

        if (header.sort_order != -1) {
          this.removeFromSort(header);
        }
      }

      return arg;
    },
    toggleItems(isOpen, group) {
      this.displayed_items = {};

      if (isOpen) {
        this.groupedItems.push(group);
      } else {
        this.groupedItems = this.groupedItems.filter((item) => item != group);
      }
    },
    filterAndRefresh(value, search, item) {
      this.displayed_items = {};

      return value != null && search != null && value.toString().indexOf(search) !== -1;
    },
  },
  props: [
    "users",
    "companyFields",
    "columnSettings",
    "currentUser",
    "domain",
    "appFolder",
  ],
};
</script>

<style scoped>
.sticky-header {
  position: sticky;
  z-index: 2;
  top: 0;
}

.sticky-group-header {
  position: sticky;
  z-index: 1;
  top: 3rem;
  background-color: #c7c7c7;
}

.v-data-table /deep/ .v-data-table__wrapper {
  overflow: unset;
}

.v-input /deep/ label {
  margin-bottom: 0;
}
</style>

<style>
tbody tr:nth-of-type(odd) {
  background-color: rgba(0, 0, 0, 0.05);
}

.inline-items {
  display: flex;
}
</style>
