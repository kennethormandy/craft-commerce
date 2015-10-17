Craft.Commerce = Craft.Commerce || {};

Craft.Commerce.OrderEdit = Garnish.Base.extend({
    $container: null,
    orderId: null,
    $billingAddress: null,
    $shippingAddress: null,
    $statusUpdate: null,
    statusUpdateModal: null,
    init: function(args, settings){

        this.orderId = args.orderId;
        this.$container = args.container;
        this.$billingAddress = this.$container.find('.billingAddress');
        this.$shippingAddress = this.$container.find('.shippingAddress');
        this.$statusUpdate = this.$container.find('.updatestatus');

        var $editBillingAddressBtn = this.$billingAddress.find('.edit.btn').click(function (e) {
            e.preventDefault();
            if (!this.modal) {
                this.modal = new Craft.Commerce.EditAddressModal(this, {});
            } else {
                this.modal.show();
            }
        });

        var $editShippingAddressBtn = this.$billingAddress.find('.edit.btn').click(function (e) {
            e.preventDefault();
            if (!this.modal) {
                this.modal = new Craft.Commerce.EditAddressModal(this, {});
            } else {
                this.modal.show();
            }
        });

        this.addListener(this.$statusUpdate, 'click', function (ev) {
            ev.preventDefault();
            this._openCreateUpdateStatusModal();
        });
    },
    _openCreateUpdateStatusModal: function(){
        if (!this.statusUpdateModal) {
            var id = this.orderId;
            var handle = this.$statusUpdate.data('orderstatushandle');
            var statuses = this.$statusUpdate.data('orderstatuses');
            this.statusUpdateModal = new Craft.Commerce.UpdateOrderStatusModal(handle,statuses, {
                onSubmit: function(data){
                    data.orderId = id;
                    Craft.postActionRequest('commerce/orders/updateStatus', data, function (response) {
                        if (response.success) {
                            location.reload(true);
                        } else {
                            this.$error.html(response.error);
                        }
                    });
                }
            });
        } else {
            this.statusUpdateModal.show();
        }
    }
});