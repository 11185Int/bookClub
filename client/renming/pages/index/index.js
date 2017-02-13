// index.js
// 获取应用实例
var app = getApp()
var util = require('../../utils/util.js')
Page({
  data: {
    userInfo: {},
    bookInfo: []
  },
  // 事件处理函数
  borrowBookByScaned: function () {
    wx.scanCode({
      success: function (res) {
        if (res.result) {
          wx.request({
            url: app.globalData.links.doubanIsbn + res.result,
            method: 'GET',
            success: function (res) {
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
              util.popup({text:'获取图书信息失败'})
            }
          })
        }
      }
    })
  },
  viewBookDetail: (e) => {
    wx.navigateTo({
      url: `../bookDetail/bookDetail?id=${e.target.dataset.id}`
    })
  },
  onLoad: function () {
    // var that = this
    // 调用应用实例的方法获取全局数据
    // app.getUserInfo(function(userInfo){
    //   //更新数据
    //   that.setData({
    //     userInfo:userInfo
    //   })
    // })
    const bookInfoData = [
      { id: '1', img: 'https://img5.doubanio.com/lpic/s29166756.jpg', text: '当呼吸化为空气' },
      { id: '2', img: 'https://img3.doubanio.com/lpic/s29126814.jpg', text: '会消失的人' },
      { id: '3', img: 'https://img1.doubanio.com/lpic/s29212018.jpg', text: '醉酒的植物学家' },
      { id: '4', img: 'https://img1.doubanio.com/lpic/s29308158.jpg', text: '背对世界' },
      { id: '5', img: 'https://img3.doubanio.com/lpic/s29295915.jpg', text: '这世界偷偷爱着你' },
      { id: '6', img: 'https://img3.doubanio.com/lpic/s29251210.jpg', text: '罗辑思维：我懂你的' },
      { id: '7', img: 'https://img1.doubanio.com/lpic/s29306168.jpg', text: '山海经' },
      { id: '8', img: 'https://img5.doubanio.com/lpic/s29115076.jpg', text: '算法新解' },
      { id: '9', img: 'https://img3.doubanio.com/lpic/s29205454.jpg', text: '叶锦添的创意美学' },
      { id: '10', img: 'https://img1.doubanio.com/lpic/s29329329.jpg', text: '贵客' }
    ]

    this.setData({
      bookInfo: bookInfoData
    })
  }
})
