$(document).ready(function() {
    $('.breadcrumb.clearfix').empty();
    $('.breadcrumb.clearfix').hide();

    $('#close_cart').on('click', function() {
        $('.closeable').slideToggle();
        $('#close_cart_sign').toggleClass('down');
        $('#close_cart_sign').toggleClass('up');
    });
});