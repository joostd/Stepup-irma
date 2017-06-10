$(function() {
    var success_fun = function(attrsjwt) {
        $.ajax({
            type: "POST",
            url: self + "verify-attributes?attrs=" + attrsjwt
        }).done(function() {
            window.location = return_url;
        }).fail(function(jqXHR, textStatus) { // TODO improve error handling
            console.log("IRMA fail: " + textStatus);
        });
    };

    $("#irma").on("click", function() { // TODO handle cancel and error
        IRMA.verify(jwt, success_fun, null, null);
    });

});
