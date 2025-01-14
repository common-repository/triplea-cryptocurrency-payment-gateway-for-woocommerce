(function ($) {
    "use strict";

    let selector = ".wc_payment_method.payment_method_triplea_payment_gateway";

    // GET  -> triplea_ajax_action(url, callback, "GET", null)
    // POST -> triplea_ajax_action(url, callback, "POST", data)
    window.triplea_ajax_action = function (
        url,
        callback,
        _method,
        _data,
        sendJSON = true
    ) {
        let xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                try {
                    var data = JSON.parse(xmlhttp.responseText);
                } catch (err) {
                    console.warn(
                        err.message + " in " + xmlhttp.responseText,
                        err
                    );
                    return;
                }
                callback(data);
            }
        };
        xmlhttp.open(_method, url, true);
        if (!sendJSON) {
            xmlhttp.setRequestHeader(
                "Content-Type",
                "application/x-www-form-urlencoded;charset=UTF-8"
            );
        }
        xmlhttp.send(_data);
    };

    window.triplea_getPaymentFormData = function () {
        const ajaxUrlNode = document.getElementById(
            "triplea-payment-gateway-payment-form-request-ajax-url"
        );
        const ajaxUrl = ajaxUrlNode
            ? ajaxUrlNode.getAttribute("data-value")
            : null;

        if (!ajaxUrl) {
            console.warn("missing ajax url for payment form data request");
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
            return;
        }

        const url = ajaxUrl;
        const method = "POST";
        let data = $(selector).closest("form").serialize();
        triplea_ajax_action(
            url,
            triplea_getPaymentFormDataCallback,
            method,
            data,
            false
        );
    };

    window.triplea_getPaymentFormDataCallback = function (response) {
        if (response.data && response.success === false) {
            var messageItems = response.data.messages
                .map(function (message) {
                    return "<li>" + message + "</li>";
                })
                .join("");

            showError(
                '<ul class="woocommerce-error" role="alert">' +
                    messageItems +
                    "</ul>",
                selector
            );
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
            return null;
        } else if (
            response.result &&
            response.result === "failure" &&
            response.messages &&
            typeof response.messages === "string"
        ) {
            showError(response.messages, selector);
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
            return null;
        }

        if (!response || !response.status || response.status !== "ok") {
            console.warn("error occured when requesting payment form data");
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
            return null;
        }

        $("#triplea_embedded_payment_form_btn").hide();
        $("#triplea_embedded_payment_form_loading_txt").hide();

        triplea_freeze_checkout_form();

        triplea_createHiddenInputData(
            "triplea_order_txid",
            response.order_txid
        );
        triplea_createHiddenInputData(
            "triplea_embedded_payment_form_url",
            response.url
        );
        triplea_createHiddenInputData(
            "triplea_payment_reference",
            response.payment_reference
        );
        triplea_createHiddenInputData(
            "triplea_access_token",
            response.access_token
        );

        triplea_displayEmbeddedPaymentForm();
    };
    // Function to remove the beforeunload event listener
    function removeBeforeUnload() {
        window.removeEventListener("beforeunload", handleBeforeUnload);
    }

    // Function to handle the beforeunload event
    function handleBeforeUnload(e) {
        var message = "Are you sure you want to leave?";
        e.returnValue = message;
        return message;
    }
    window.triplea_displayEmbeddedPaymentForm = function () {
        const iframeUrlNode = document.getElementById(
            "triplea_embedded_payment_form_url"
        );
        if (!iframeUrlNode || !iframeUrlNode.value) {
            return;
        }

        const btnNode = document.getElementById(
            "triplea_embedded_payment_form_btn"
        );
        btnNode.style.display = "none";

        let iframeUrl = iframeUrlNode.value;

        //display the order currency in the payment form
        if (iframeUrl.indexOf("?") > 0) {
            //iframeUrl += '&order_details=hide&order_currency=hide';
            iframeUrl += "&order_details=hide";
        } else {
            //iframeUrl += '?order_details=hide&order_currency=hide';
            iframeUrl += "?order_details=hide";
        }

        const iframeNode = document.createElement("iframe");
        iframeNode.setAttribute("id", "triplea_embedded_payment_form_iframe");
        iframeNode.setAttribute("name", "triplea_embedded_payment_form_iframe");
        iframeNode.setAttribute("scrolling", "no");
        iframeNode.style.width = "100%";
        iframeNode.style.maxWidth = "100%";
        iframeNode.style.height = "400px";
        iframeNode.style.overflowY = "hidden !important";
        iframeNode.style.overflowX = "hidden !important";
        iframeNode.style.overflow = "hidden";
        iframeNode.style.border = "none";
        iframeNode.style.margin = "30px 0";

        iframeNode.src = iframeUrl;

        // Find the node after which to insert the iframe embedded payment form.
        let insertionNode = document.getElementById(
            "triplea_embedded_payment_form_btn"
        );
        insertionNode.parentNode.insertBefore(iframeNode, btnNode);
        window.addEventListener("beforeunload", handleBeforeUnload);

        (function () {
            window.addEventListener(
                "message",
                (event) => {
                    //console.debug('message received:', event.data);
                    let iframe = document.getElementById(
                        "triplea_embedded_payment_form_iframe"
                    );
                    if (typeof event.data === "string") {
                        const response = event.data.split("|");
                        if (!iframe) {
                            console.warn(
                                "Cannot catch iframe event, iframe node issue"
                            );
                        } else if (response[0] === "triplea.frameResized") {
                            // if (parseInt(response[1]) > 400) {
                            iframe.style.height = response[1] + "px";
                            // }
                        } else if (response[0] === "triplea.paymentTooLittle") {
                            // console.debug('Catching ' + response[0] + ' event');
                        } else if (response[0] === "triplea.paymentSuccess") {
                            // console.debug('Catching ' + response[0] + ' event');
                            removeBeforeUnload();
                            triplea_submitForm();
                        } else if (response[0] === "triplea.formExpired") {
                            // console.debug('Catching ' + response[0] + ' event');
                            removeBeforeUnload();
                            triplea_displayBackupPlaceOrderBtn();
                        }
                    }
                },
                false
            );
        })();
    };

    window.triplea_displayBackupPlaceOrderBtn = function () {
        // Display a "Place order anyway" button and some text under it.
        if (document.getElementById("triplea_place_order_form_expired_btn")) {
            return;
        }

        const iframeNode = document.getElementById(
            "triplea_embedded_payment_form_iframe"
        );

        iframeNode.insertAdjacentHTML(
            "afterend",
            '<p style="text-align: center;padding: 10px 5px 15px;font-size: 90%;">It will be updated automatically once payment is detected.</p>'
        );
        iframeNode.insertAdjacentHTML(
            "afterend",
            '<button id="triplea_place_order_form_expired_btn" onclick="triplea_submitForm()" type="button" class="button alt" style="margin: 0 auto;display: block;">Place order anyway</button>'
        );
        iframeNode.insertAdjacentHTML(
            "afterend",
            '<p style="text-align: center;padding: 25px 5px 15px;">Did the form not detect your payment in time?</p>'
        );
    };

    window.triplea_submitForm = function (delay = 1500) {
        $("#place_order").css("opacity", 1.0);
        $("#place_order").css("visibility", "initial");
        const timer = setTimeout(function () {
            let submitBtn = document.getElementById("place_order");
            if (submitBtn) {
                submitBtn.click();
            } else {
                console.warn(
                    "Missing submit button. Could not submit form to place order."
                );
            }
        }, delay);
    };

    window.triplea_createHiddenInputData = function (inputId, inputValue) {
        let hiddenInput;

        if (!!document.getElementById(inputId)) {
            // Update hidden input element with id and value
            hiddenInput = document.getElementById(inputId);
            hiddenInput.value = inputValue;
        } else {
            // create hidden input element with id and value
            hiddenInput = document.createElement("input");
            hiddenInput.setAttribute("id", inputId);
            hiddenInput.setAttribute("name", inputId);
            hiddenInput.setAttribute("type", "hidden");
            hiddenInput.value = inputValue;

            // Find checkout form, append input to the form
            let checkoutForm = document.getElementsByClassName(
                "checkout woocommerce-checkout"
            )["checkout"];
            checkoutForm.appendChild(hiddenInput);
        }
    };

    function orderpay_checkout(response) {
        console.log(response);
    }

    window.triplea_validateCheckout = function (elem) {
        $("#triplea_embedded_payment_form_btn").hide();
        $("#triplea_embedded_payment_form_loading_txt").show();

        if (elem.classList.contains("triplea-order-pay")) {
            let url =
                triplea_object.ajax_url +
                "?action=triplea_orderpay_payment_request";
            let data = { orderid: elem.dataset.id };

            //console.log("Sending AJAX request to: ", url);
            //console.log("Data being sent: ", data);

            jQuery.ajax({
                type: "POST",
                url: url,
                data: data,
                success: function (response) {
                    //console.log( "AJAX request successful. Response: ",response);
                    let hiddenInput = document.createElement("input");
                    hiddenInput.setAttribute(
                        "id",
                        "triplea_embedded_payment_form_url"
                    );
                    hiddenInput.setAttribute(
                        "name",
                        "triplea_embedded_payment_form_url"
                    );
                    hiddenInput.setAttribute("type", "hidden");
                    hiddenInput.value = response;

                    // Find checkout form, append input to the form
                    let orderReviewForm =
                        document.getElementById("order_review");
                    orderReviewForm.appendChild(hiddenInput);
                    triplea_displayEmbeddedPaymentForm();
                },
                error: function (xhr, status, error) {
                    console.error(
                        "AJAX request failed. Status: ",
                        status,
                        "Error: ",
                        error
                    );
                    alert("Something went wrong: " + error);
                },
            });
            return true;
        }
        let checkoutCheckUrlNode = document.getElementById(
            "triplea-payment-gateway-start-checkout-check-url"
        );

        if (checkoutCheckUrlNode) {
            let url = checkoutCheckUrlNode.getAttribute("data-value");
            if (url) {
                let callback = triplea_validateCheckoutCallback;

                // Clear any errors from previous attempt.
                $(".woocommerce-error", selector).remove();

                let data = $(selector).closest("form").serialize();

                // Call URL
                triplea_ajax_action(url, callback, "POST", data, false);

                // Upon return, if not successful let it display error messages...
                // If successful, trigger Cryptocurrency payment form display.
            }
        } else {
            console.error("Checkout validation callback URL not found.");
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
        }
    };
    function triplea_freeze_checkout_form() {
        var billingFieldsDiv = document.querySelector(
            ".woocommerce-billing-fields"
        );

        // Apply the blur effect using inline styles
        billingFieldsDiv.style.webkitFilter = "blur(5px)";
        billingFieldsDiv.style.mozFilter = "blur(5px)";
        billingFieldsDiv.style.oFilter = "blur(5px)";
        billingFieldsDiv.style.msFilter = "blur(5px)";
        billingFieldsDiv.style.filter = "blur(1px)";
        billingFieldsDiv.style.pointerEvents = "none";
        billingFieldsDiv.style.position = "relative";
    }
    function triplea_validateCheckoutCallback(response) {
        if (
            response.data.messages &&
            response.data.messages.error &&
            response.data.messages.error.length > 0
        ) {
            let messageItems = response.data.messages.error
                .map(function (message) {
                    return "<li>" + message.notice + "</li>";
                })
                .join("");

            showError(
                '<ul class="woocommerce-error" role="alert">' +
                    messageItems +
                    "</ul>",
                selector
            );
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
            return null;
        }
        if (response.data && response.success === false) {
            let messageItems = response.data.messages
                .map(function (message) {
                    return "<li>" + message + "</li>";
                })
                .join("");

            showError(
                '<ul class="woocommerce-error" role="alert">' +
                    messageItems +
                    "</ul>",
                selector
            );
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
            return null;
        } else if (
            response.result &&
            response.result === "failure" &&
            response.messages &&
            typeof response.messages === "string"
        ) {
            showError(response.messages, selector);
            $("#triplea_embedded_payment_form_btn").show();
            $("#triplea_embedded_payment_form_loading_txt").hide();
            return null;
        }

        // Clear any errors from previous attempt.
        $(".woocommerce-error").remove();

        triplea_getPaymentFormData();
    }

    // Show error notice at top of checkout form, or else within button container
    function showError(errorMessage, selector) {
        var $container = $(".woocommerce-notices-wrapper, form.checkout");

        if (!$container || !$container.length) {
            $(selector).prepend(errorMessage);
            return;
        } else {
            $container = $container.first();
        }

        // Adapted from https://github.com/woocommerce/woocommerce/blob/ea9aa8cd59c9fa735460abf0ebcb97fa18f80d03/assets/js/frontend/checkout.js#L514-L529
        $(
            ".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message"
        ).remove();
        $container.prepend(
            '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
                errorMessage +
                "</div>"
        );
        $container
            .find(".input-text, select, input:checkbox")
            .trigger("validate")
            .blur();

        var scrollElement = $(".woocommerce-NoticeGroup-checkout");
        if (!scrollElement.length) {
            scrollElement = $container;
        }

        if ($.scroll_to_notices) {
            $.scroll_to_notices(scrollElement);
        } else {
            // Compatibility with WC <3.3
            $("html, body").animate(
                {
                    scrollTop: $container.offset().top - 100,
                },
                1000
            );
        }

        $(document.body).trigger("checkout_error");
    }

    function paymentMethodAction() {
        if (
            $(
                'form[name="checkout"] input[name="payment_method"]:checked'
            ).val() === "triplea_payment_gateway"
        ) {
            $("#place_order").css("opacity", 0.25);
            $("#place_order").css("visibility", "hidden");
        } else if (
            $('#order_review input[name="payment_method"]:checked').val() ===
            "triplea_payment_gateway"
        ) {
            $("#place_order").css("opacity", 0.25);
            $("#place_order").css("visibility", "hidden");
        } else {
            $("#place_order").css("opacity", 1.0);
            $("#place_order").css("visibility", "initial");
        }
    }

    $(document).ready(function (e) {
        if (
            $('#order_review input[name="payment_method"]:checked').val() ==
            "triplea_payment_gateway"
        ) {
            paymentMethodAction();
        }
        $('input[name="payment_method"]').change(function () {
            paymentMethodAction();
        });
        $("body").on("updated_checkout", function () {
            //console.debug('event updated_checkout !');
            paymentMethodAction();
            $('input[name="payment_method"]').change(function () {
                paymentMethodAction();
            });
        });
    });
})(jQuery);
