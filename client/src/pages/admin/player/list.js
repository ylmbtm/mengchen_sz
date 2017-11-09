import '../common.js'
import MyVuetable from '../../../components/MyVuetable.vue'
import FilterBar from '../../../components/MyFilterBar.vue'
import MyToastr from '../../../components/MyToastr.vue'
import TableActions from './components/TableActions.vue'

Vue.component('table-actions', TableActions)

new Vue({
  el: '#app',
  components: {
    MyVuetable,
    FilterBar,
    MyToastr,
  },
  data: {
    eventHub: new Vue(),
    activatedRow: {},
    topUpData: {
      type: {
        1: '房卡',
        2: '金币',
      },
      typeId: 1,
      amount: null,
    },

    tableUrl: '/admin/api/game/players',
    tableTrackBy: 'id',
    tableFields: [
      {
        name: 'id',
        title: '玩家ID',
      },
      {
        name: 'nickname',
        title: '玩家昵称',
      },
      {
        name: 'ycoins',
        title: '房卡数量',
      },
      {
        name: 'ypoints',
        title: '金币数量',
      },
      {
        name: 'state',
        title: '账号状态',
      },
      {
        name: 'create_time',
        title: '创建时间',
      },
      {
        name: 'last_time',
        title: '最后登录时间',
      },
      {
        name: '__component:table-actions',
        title: '操作',
        titleClass: 'text-center',
        dataClass: 'text-center',
      },
    ],
    tableSortOrder: [      //默认的排序
      {
        field: 'id',
        sortField: 'id',
        direction: 'desc',
      },
    ],
  },

  methods: {
    topUpPlayer () {
      let _self = this
      let apiUrl = `/admin/api/top-up/player/${_self.activatedRow.id}/${_self.topUpData.typeId}/${_self.topUpData.amount}`
      let toastr = this.$refs.toastr

      axios({
        method: 'POST',
        url: apiUrl,
        validateStatus: function (status) {
          return status === 200 || status === 422
        },
      })
        .then(function (response) {
          if (response.status === 422) {
            toastr.message(JSON.stringify(response.data), 'error')
          } else {
            response.data.error
              ? toastr.message(response.data.error, 'error')
              : toastr.message(response.data.message)
            _self.topUpData.amount = null
            //_self.$root.eventHub.$emit('vuetableRefresh')  //重新刷新表格
          }
        })
        .catch(function (err) {
          alert(err)
        })
    },
  },

  mounted: function () {
    let _self = this

    this.$root.eventHub.$on('topUpPlayerEvent', (data) => _self.activatedRow = data)
    this.$root.eventHub.$on('vuetableDataError', (data) => alert(data.error))
  },
})
