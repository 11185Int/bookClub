// pages/scanResult/scanResult.js
const app = getApp();
var onfire = require('../../utils/onfire');
Page({
  data:{
    info:{}
  },
  onLoad:function(options){
    console.log('onload');
    var that = this;
    onfire.fire('scanisbn',{context:this});
    // console.log(app.globalData.currentBookInfo);
    // this.setData({
    //   info: app.globalData.currentBookInfo
    // });
    console.log(this.data.info)
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
  }
})