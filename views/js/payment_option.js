$(document).ready(function () {
    var checkbox = $("#conditions_to_approve\\[terms-and-conditions\\]").prop('checked');
    if (checkbox) {
        $("#payment-confirmation button[type='submit']").prop('disabled', false).removeClass('disabled');
    }
});
