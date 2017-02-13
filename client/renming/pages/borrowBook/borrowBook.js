// pages/borrowBook/borrowBook.js
Page({
  data: {
    sharer: null
  },
  onLoad: function (options) {
    // 页面初始化 options为页面跳转所带来的参数
  },
  onReady: function () {
    // 页面渲染完成
  },
  onShow: function () {
    // 页面显示
  },
  onHide: function () {
    // 页面隐藏
  },
  onUnload: function () {
    // 页面关闭
  },
  radioChange: function (e) {
    console.log('radio发生change事件，携带value值为：', e.detail.value)
    this.setData({
      sharer: e.detail.value
    })
  },
  sureBorrow: function () {
    if (this.data.sharer) {
      console.log('sure borrow')
    } else {
      console.log('Please choose sharer')
    }

  },
  cancelBorrow: function () {
    console.log('cancel borrow')
    wx.navigateBack()
  }
})