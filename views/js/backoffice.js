$(document).ready(function() {
    
    $('#charge-easy-subscription').on('click', function() {
        if (subscriptionId == '') {
            alert('No subscription ID');
        } else {
            $.ajax({
                type: "POST",
                url: link_to_controller,
                dataType: 'json',
                data: {
                    ajax: '1',
                    tab: 'AdminChargeSubscription',
                    action: 'chargeSubscription',
                    subscriptionId: subscriptionId,
                    id_order: id_order
                },
                success: function(res)
                {
                    if (res.code == 'NOK') {
                        showErrorMessage('Charge Failed');
                    } else {
                        showSuccessMessage('Charge OK');
                    }
                    
                    $.ajax({
                        type: "POST",
                        url: link_to_controller,
                        dataType: 'json',
                        data: {
                            ajax: '1',
                            tab: 'AdminChargeSubscription',
                            action: 'changeChargeMessage',
                            subscriptionId: subscriptionId,
                            id_order: id_order,
                            message: res.chargeId
                        },
                        success: function(res)
                        {
                            if (res.result == 'ok') {
                                location.reload();
                            }
                        }
                    });
                }
            });
        }
    });
});