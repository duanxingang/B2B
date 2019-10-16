define(['core', 'tpl', 'biz/goods/picker', 'biz/goods/wholesalePicker'], function(core, tpl, picker, wholesalePicker) {
    var modal = {
        page: 1,
        merchid: 0
    };
    modal.init = function(args) {
        modal.merchid = args.merchid || 0;
        if (typeof(window.cartcount) !== 'undefined') {
            picker.changeCartcount(window.cartcount)
        }
        if (!modal.toGoods) {
            modal.page = 1
        } else {
            modal.toGoods = false
        }
        modal.getList();
        modal.bindEvents();
        $('.fui-content').infinite({
            onLoading: function() {
                modal.getList()
            }
        })
    };
    modal.bindEvents = function() {
        $("a").click(function() {
            modal.toGoods = true
        });
        $('.buy').unbind('click').click(function() {
            var goodsid = $(this).closest('.fui-goods-item').data('goodsid');

            //控销 2019-6-28
            core.json('goods/picker/sell', {
                id: goodsid
            }, function(ret) {

                if (ret.status == 0) {
                    FoxUI.toast.show(ret.result.message);
                    return
                }else{
                    var type = $(this).closest('.fui-goods-item').data('type');
                    var is_bargain = $(this).hasClass('bargain-btn');
                    if (is_bargain) {
                        type = 20
                    }
                    if (type == 20) {
                        location.href = core.getUrl('goods/detail', {
                            id: goodsid
                        })
                    } else {
                        if ($(this).data('type') == 4) {
                            wholesalePicker.open({
                                goodsid: goodsid
                            })
                        } else {
                            picker.open({
                                goodsid: goodsid,
                                total: 1
                            })
                        }
                    }
                }

            }, true, false);


        })
    };
    modal.getUrl = function() {
        var url = '';
        if (modal.merchid == 0) {
            url = 'get_recommand'
        } else {
            url = 'merch/get_recommand'
        }
        return url
    };
    var isloading = false;
    modal.getList = function() {
        if (isloading) {
            return
        }
        isloading = true;
        var url = modal.getUrl();
        var param = {
            page: modal.page,
            merchid: modal.merchid
        };
        core.json(url, param, function(ret) {
            var result = ret.result;
            if (result.total <= 0) {
                $('.fui-content').infinite('stop');
                $('.fui-content').lazyload();
                isloading = false
            } else {
                $('.fui-content').infinite('init');
                if (result.list.length <= 0 || result.list.length < result.pagesize) {
                    $('.fui-content').infinite('stop')
                }
                if (result.page == 1) {
                    var c = "#recommand"
                } else {
                    var c = "#recommand .fui-goods-group"
                }
                modal.page++;
                core.tpl(c, 'tpl_recommand', result, modal.page > 1);
                modal.bindEvents();
                isloading = false
            }
        })
    };
    return modal
});