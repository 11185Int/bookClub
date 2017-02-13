var onfire = require('./onfire')

function scanCode (opts) {
  wx.scanCode({
    success: function (res) {
      if (res.result) {
        wx.request({
          url: 'https://api.douban.com/v2/book/isbn/' + res.result,
          method: 'GET', // OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, CONNECT
          // header: {}, // 设置请求的 header
          success: function (res) {
            // app.globalData.currentBookInfo = res.data
            console.log(res.data)
            onfire.on('scanisbn', (args) => {
              args.context.setData({
                info: res.data
              })
            })
            wx.navigateTo({
              url: '../scanResult/scanResult'
            })
          },
          fail: function () {
            console.log('douban error')
          },
          complete: function () {
            // complete
          }
        })
      }
    },
    fail: function () {
      console.log('error')
    },
    complete: function () {
      console.log('complete')
    }
  })
}

module.exports = {}
