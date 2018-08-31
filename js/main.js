
$(function () {
    $('body').on('click', '#formContract', function () {

        $("#error").css("display", "none");

        var customer = $("#customerNameOrId").val();
        var form = $("form").serialize();
        console.log(form);
        if(customer.length > 0){
            $.ajax({
                type: "POST",
                url: "/ajax.php",
                data: form,
                success: function (data) {
                    $("#contractsTable").html(data);
                    if (data == 0 || data == '' || data == null || !data) {
                        $("#error").css("display", "block");
                    }
                },
                error: function () {
                    //alert('error handing here');
                }
            });
        } else {
            $("#error").css("display", "block");
        }
        return false;
    });
});