declare let $;
declare let Vue;

let app = new Vue({
    el: '#app',
    data: {
        message: 'Hello Vue!'
    }
});

function fetchOnlinePlayers() {
    $.get( "127.0.0.1:5200", function( data ) {
        $( ".result" ).html( data );
        alert( "Load was performed." );
    });
}