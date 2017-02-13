// pages/bookDetail/bookDetail.js
var onfire = require('../../utils/onfire');
Page({
  data:{},
  onLoad:function(options){
    // 页面初始化 options为页面跳转所带来的参数
    console.log(options.query)
  },
  onReady:function(){
    // 页面渲染完成
  },
  onShow:function(){
    // 页面显示
  },
  onHide:function(){
    // 页面隐藏
  },
  onUnload:function(){
    // 页面关闭
  },  
  borrowBookByScaned: function(){
    console.log(111)
    wx.scanCode({
      success: function (res) {

        if (res.result) {
          wx.request({
            url: 'https://api.douban.com/v2/book/isbn/' + res.result,
            method: 'GET', // OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, CONNECT
            // header: {}, // 设置请求的 header
            success: function (res) {
              // app.globalData.currentBookInfo = res.data;
              console.log(res.data)
              onfire.on('scanisbn', (args) => {
                args.context.setData({
                  info: res.data
                });
              });
              wx.navigateTo({
                url: '../borrowBook/borrowBook'
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
})