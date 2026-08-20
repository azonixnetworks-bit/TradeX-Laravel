// TradeX Admin main JS 
document.addEventListener(
    'DOMContentLoaded',
    function () {

        document
            .querySelectorAll('.nav-disabled')
            .forEach(function (link) {

                link.addEventListener(
                    'click',
                    function (event) {
                        event.preventDefault();
                    }
                );

            });

    }
);