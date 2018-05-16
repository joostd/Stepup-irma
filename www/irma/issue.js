$(function() {
    var success_fun = function() {
        $.ajax({
            type: "POST",
            url: self + "attributes-issued"
        }).done(function() {
            window.location = return_url;
        }).fail(function(jqXHR, textStatus) { // TODO improve error handling
            console.log("IRMA fail: " + textStatus);
        });
    };

    $("#irma").on("click", function() { // TODO handle cancel and error
        IRMA.issue(jwt, success_fun, null, null);
    });

});
