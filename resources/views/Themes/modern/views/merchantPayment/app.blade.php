<!DOCTYPE html>
<html lang="en">
    <head>
      <title>@lang('message.express-payment-form.merchant-payment')</title>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Ensures optimal rendering on mobile devices. -->
      <meta http-equiv="X-UA-Compatible" content="IE=edge" /> <!-- Optimal Internet Explorer compatibility -->
      <link rel="stylesheet" href="{{ asset('public/backend/bootstrap/dist/css/bootstrap.css') }}">
      <link rel="stylesheet" type="text/css" href="{{asset('public/backend/font-awesome/css/font-awesome.min.css')}}">
      <script src="{{ asset('public/backend/jquery/dist/jquery.js') }}"></script>
      <script src="{{ asset('public/backend/bootstrap/dist/js/bootstrap.min.js') }}"></script>
      <script type="text/javascript">
        var SITE_URL = "{{url('/')}}";
      </script>

      <style>
        @media screen and (max-width: 600px) {
          .float-bloc{
            float:none !important;
          }
          .col-6 {
            max-width: 100%;
          }
        }
        .float-bloc{
          float:left;
        }
        .nav-link:focus {
          text-decoration: none;
          border:1px solid grey !important;
        }
        h4{
            color: #173D7A;
            font-weight: 700;
            font-size: 32px;
            transition: .2s;
        }
        /* --- pay-method --- */
        .plan-card-group {
          display: block;
          margin: auto;
          width: 100%;
          align-items: center;
          justify-content: space-around;
          flex-wrap: wrap;
        }
        .radio-card {
          width: 100%;
          margin-bottom: 30px;
        }
        .radio-card label {
          display: flex;
          justify-content: center;
          align-items: center;
          padding: 0;
          height: 140px;
          background: #f9f9f9;
          border: 1px solid #e4e4e4;
          color: #003d2e;
          border-radius: 5px;
          transition: all 0.2s ease-in-out;
        }
        .radio-card label:hover {
          cursor: pointer;
          background: #ececec;
        }
        .radio-card label:active {
          background: #ececec;
          color: #ccf5eb;
        }
        .radio-card input[type="radio"]:checked ~ label {
          background: rgba(127, 103, 170, 0.2);
          color: white;
          border: 1px solid #cac8c8;
        }
        .card-title {
          display: block;
          font-size: 20px;
          width: 100%;
        }
        .planes-radio {
          display: none;
        }
        #clictopay{
          display: none;
        }
        #cp-payment{
          display: none;
        }
        .photo-gris  {
          filter: grayscale(1);
          -webkit-filter: grayscale(1);
        }

        #plan-finalizar {
          display: block;
          margin: auto;
          padding: 15px 25px;
          border: none;
          border-radius: 5px;
          background: rgba(79, 179, 110, 0.35);
          color: white;
          font-size: 16px;
          transition: all 0.5s;
        }
        #plan-finalizar:hover {
          cursor: pointer;
          background: #00c291;
          color: white;
        }
        #plan-finalizar:focus, #plan-finalizar:active {
          outline: none;
          background: rgba(79, 179, 110, 0.35);
        }
        #plan-finalizar:disabled {
          background: #ddd;
          cursor: default;
        }
        fieldset {
          border: none;
        }
        legend {
          padding: 10px;
          font-size: 24px;
          font-weight: 300;
        }
        .padding-10 {
          padding: 10px;
        }
        .padding-20 {
          padding: 20px;
        }
        .padding-35 {
          padding: 35px;
        }
        .radio-card .fee { background: #7f67aa none repeat scroll 0 0;
          color: #fff;
          font-size: 12px;
          font-weight: bold;
          letter-spacing: 1px;
          padding: 0px 10px;
          position: absolute;
          right: 15px;
          top: 0;
          z-index: 4;
          line-height: 25px;
        }

        /*logo -- css*/
        .setting-img{
          overflow: hidden;
          max-width: 100%;
        }
        .img-wrap-general-logo {
          /*width: 300px;*/
          overflow: hidden;
          margin: 5px;
          background: rgba(74, 111, 197, 0.9) !important;
          /*height: 100px;*/
          max-width: 100%;
        }
        .img-wrap-general-logo > img {
          max-width: 100%;
          height: auto !important;
          max-height: 100%;
          width: auto !important;
          object-fit: contain;
        }
        .text-danger {
          color: red;
          font-weight: bold;
        }
        /*logo -- css*/

      </style>

      <style type="text/css">
        /*error class for MTS/Pay Money*/
        .error {
          color:red;
          float: left;
        }
      </style>
    </head>

    <body>

    <div class="container">
      <div class="row">
        <div class="col-md-4 col-sm-4"></div>
        <div class="col-md-4 col-sm-4"></div>
        <!-- <div class="col-md-2 col-sm-4">
                <h2>@lang('message.footer.language')</h2>
                <div class="form-group">
                  <select class="form-control" id="lang">
                    @foreach (getLanguagesListAtFooterFrontEnd() as $lang)
            <option {{ Session::get('dflt_lang') == $lang->short_name ? 'selected' : '' }}
                    value='{{ $lang->short_name }}'> {{ $lang->name }}
                    </option>
                    @endforeach
                </select>
              </div>
            </div> -->
      </div>
    </div>

    <div class="container text-center">
      <div class="panel panel-default box-shadow" style="margin-top: 15px;">
        <div class="panel-body">
          <div class="col-md-12 ">
            @if($isMerchantAvailable)
              <h1>
                <?php
                  $amount = isset($paymentInfo['amount']) ? $paymentInfo['amount'] : 0;
                ?>
                {{ moneyFormat($merchant->currency->code, formatNumber($amount)) }}
              </h1>
              <br>
              <h4>{{$paymentInfo['item_name'] ? $paymentInfo['item_name']:""}}</h4> 
            @endif
          </div>
          <div class="col-md-12">
            <div class="bs-callout bs-callout-danger">
              @if(!$isMerchantAvailable)
                <h4 style="color:red">@lang('message.express-payment-form.merchant-not-found')</h4>
              @else 
                <p>@lang('message.express-payment-form.merchant-found')</p>
              @endif
            </div>
          </div>
          <div class="col-md-12">
            @if(Auth::user())
            <div class="col-6 float-bloc"  id="cp-payment-action2">
              <img class="img-responsive" src='{{asset("public/images/payment_gateway/3.png")}}' style="width: 100%;" alt="">
            </div>
            @else 
            <div class="col-6 float-bloc"  id="cp-payment-action">
              <img class="img-responsive" src='{{asset("public/images/payment_gateway/3.png")}}' style="width: 100%;" alt="">
            </div>
            @endif
            <div class="col-6 float-bloc"    id="clic-to-pay-action">
              <img class="img-responsive" src='{{asset("public/images/payment_gateway/5.png")}}' style="width: 100%;" alt="">
            </div>
          </div>
          <div style="clear: both;"></div>


           <!-- <div class="col-md-12">
            <div class="col-6" style="float:left">
                    <img class="img-responsive photo-gris" src='{{asset("public/images/payment_gateway/1.png")}}' alt="">
            </div>
            <div class="col-6" style="float:left">
                    <img class="img-responsive photo-gris" src='{{asset("public/images/payment_gateway/6.png")}}' alt="">
            </div>
            </div>
            <div style="clear: both;"></div>


            <div class="col-md-12">
            <div class="col-6" style="float:left">
                    <img class="img-responsive photo-gris" src='{{asset("public/images/payment_gateway/4.png")}}' alt="">
            </div>
            <div class="col-6" style="float:left">
                    <img class="img-responsive  photo-gris" src='{{asset("public/images/payment_gateway/2.png")}}' alt="">
            </div>
            </div>
            <div style="clear: both;"></div>


            <div class="col-md-12">
            <div class="col-6" style="float:left">
                    <img class="img-responsive photo-gris" src='{{asset("public/images/payment_gateway/7.png")}}' alt="">
            </div>
          -->
        </div>
        <div style="clear: both;"></div>

        <form action="{{url('payment/wepay')}}" id="wepay" name="wepay" method="POST" accept-charset="utf-8">
          <input name="merchant" value="{{isset($paymentInfo['merchant_id']) ? $paymentInfo['merchant_id'] : ''}}" type="hidden">
          <input name="merchant_uuid" value="{{isset($paymentInfo['merchant']) ? $paymentInfo['merchant'] : ''}}" type="hidden">
          <input name="amount" value="{{ $amount }}" type="hidden">
          <input name="currency" value="{{$merchant->currency->code}}" type="hidden">
          <input name="order_no" value="{{isset($paymentInfo['order_no']) ? $paymentInfo['order_no'] : ''}}" type="hidden">
          <input name="item_name" value="{{isset($paymentInfo['item_name']) ? $paymentInfo['item_name'] : ''}}" type="hidden">
        </form>

        <div class="col-md-12" id="cp-payment"  style="margin-top: 100px;margin-bottom: 100px;">
          <div class="card rounded-0">
            <div class="card-header" style="border-top: 5px solid #0b2854;">
              <h3 class="mb-0 text-left">Connectez-vous à votre compte WEPAY</h3>
            </div>
            <div class="card-body">
              <div class="flash-container"></div>      
                <form action="{{url('payment/wepay')}}" id="Mts" name="Mts" method="POST" accept-charset="utf-8">
                  {{csrf_field()}}  
                  <div class="form-group" style="text-align: left;">
                    <label for="exampleInputEmail1">@lang('message.express-payment-form.email')</label>
                    <input class="form-control" name="email" id="email" placeholder="Email" type="text" required>
                  </div>
                  <div class="form-group" style="text-align: left;">
                    <label for="exampleInputEmail1">@lang('message.express-payment-form.password')</label>
                    <input name="password" class="form-control" id="password" placeholder="********" type="password" required>
                  </div>  
                  <input name="merchant" value="{{isset($paymentInfo['merchant_id']) ? $paymentInfo['merchant_id'] : ''}}" type="hidden">
                  <input name="merchant_uuid" value="{{isset($paymentInfo['merchant']) ? $paymentInfo['merchant'] : ''}}" type="hidden">
                  <input name="amount" value="{{ $amount }}" type="hidden">
                  <input name="currency" value="{{$merchant->currency->code}}" type="hidden">
                  <input name="order_no" value="{{isset($paymentInfo['order_no']) ? $paymentInfo['order_no'] : ''}}" type="hidden">
                  <input name="item_name" value="{{isset($paymentInfo['item_name']) ? $paymentInfo['item_name'] : ''}}" type="hidden">
                  <div class="" style="text-align: left;">
                    <button type="submit" class="btn btn-primary standard-payment-submit-btn">
                      <span class="standard-payment-submit-btn-txt" style="font-weight: bolder;">
                        Connexion
                      </span>
                    </button>
                  </div>
                  <div class="signin">
                    <div class="message"  style="border: 1px solid #C7CFD3 !important;border-radius: 4px;padding: 18px;margin-top: 50px;">
                      <span>Vous n'avez pas de compte? &nbsp; </span>
                      <a href="{{url('/register')}}">Inscrivez-vous ici</a>.
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-12" id="clictopay"  style="margin-top: 100px;margin-bottom: 500px;">
          <form action="{{url('payment/clictopay')}}" id="target" name="ctp" method="POST" accept-charset="utf-8" >
            {{csrf_field()}}
            <input name="merchant" value="{{isset($paymentInfo['merchant_id']) ? $paymentInfo['merchant_id'] : ''}}" type="hidden">
            <input name="amount" value="{{ $amount }}" type="hidden">
            <input name="order_no" value="{{isset($paymentInfo['order_no']) ? $paymentInfo['order_no'] : ''}}" type="hidden">
            <input name="item_name" value="{{isset($paymentInfo['item_name']) ? $paymentInfo['item_name'] : ''}}" type="hidden">
            <div class="col-md-12 centered">
              <div class="pull-right">
                <button type="submit" class="btn btn-primary standard-payment-submit-btn">
                  <i class="spinner fa fa-spinner fa-spin" style="display: none;"></i>
                  <span class="standard-payment-submit-btn-txt" style="font-weight: bolder;">
                    Payer avec WEPAY
                  </span>
                </button>
              </div>
            </div>
          </form>
      </div>
    </div>
  </div>
  <script src="{{ theme_asset('public/js/jquery.validate.min.js') }}" type="text/javascript"></script>
  <script src="{{ theme_asset('public/js/jquery.ba-throttle-debounce.js') }}" type="text/javascript"></script>
  <script>
    
    var goToPaymentText = '{{ trans('message.express-payment-form.go-to-payment') }}';
    var forms = document.querySelectorAll('form');
    if (forms.length != 0)
    {
      forms[0].addEventListener("click", function(e)
      {
        if (e.target && e.target.nodeName == "INPUT")
        {
          hideFormsButFirst();
          setFormVisible(e.target.value);
        }
      });
      function hideFormsButFirst()
      {
        for (var i = 0; i < forms.length; ++i)
        {
          forms[i].style.display = 'none';
        }
        forms[0].style.display = 'block';
      }
      function setFormVisible(id)
      {
        id = id || "Mts";
        var form = document.getElementById(id);
        form.style.display = 'block';
      }
      function init()
      {
        hideFormsButFirst();
        setFormVisible();
      }
      init();          
    }
    $("#clic-to-pay-action").click(function(){
      $( "#target" ).submit();
    });
    $("#cp-payment-action2").click(function(){
      $( "#wepay" ).submit();
    });
    $("#cp-payment-action").click(function(){
      $("#clictopay").css("display","none");
      $("#cp-payment").css("display","block");
      $(document).scrollTop($(document).height()); 
    });
    //Language script
    $('#lang').on('change', function(e)
    {
        e.preventDefault();
        lang = $(this).val();
        url = '{{ url('change-lang') }}';
        $.ajax(
        {
            type: 'get',
            url: url,
            data:
            {
                lang: lang
            },
            success: function(msg)
            {
                if (msg == 1)
                {
                    location.reload();
                }
            }
        });
    });
    $.validator.setDefaults({
      highlight: function(element) {
        $(element).parent('div').addClass('has-error');
      },
      unhighlight: function(element) {
        $(element).parent('div').removeClass('has-error');
      },
      errorPlacement: function (error, element) {
        error.insertAfter(element);
      }
    });
    function disableSumbitCancelButtons()
    {
        // initialize spinner and disable buttons
        $('.standard-payment-submit-btn, .standard-payment-cancel-btn').attr("disabled", true).click(function (e)
        {
            e.preventDefault();
        });
        $(".fa-spin").show();
        $(".standard-payment-cancel-link").click(function (e)
        {
            e.preventDefault();
        });
        $(".standard-payment-submit-btn-txt").text('Paying...');
        form.submit();
        setTimeout(function(){
            // destroy spinner and enable buttons after 10 secs
            $('.standard-payment-submit-btn, .standard-payment-cancel-btn').removeAttr("disabled");
            $(".fa-spin").hide();
            $('.standard-payment-cancel-link').attr({
              'href': '#home',
              'data-toggle': 'tab'
            });
            $(".standard-payment-submit-btn-txt").text(goToPaymentText);
        },10000);
    }
    $('#Mts').validate(
    {
        rules: {
          email: {
              required: true,
              email: true,
          },
          password: {
              required: true,
              minlength: 6,
          },
        },
        submitHandler: function(form)
        {
            disableSumbitCancelButtons();
            form.submit();
        }
    });
    $('#2Checkout').validate(
    {
        submitHandler: function(form)
        {
            disableSumbitCancelButtons();
            form.submit();
        }
    });
    $('#PayUmoney').validate(
    {
        submitHandler: function(form)
        {
            disableSumbitCancelButtons();
            form.submit();
        }
    });
    $('#Coinpayments').validate(
    {
        submitHandler: function(form)
        {
            disableSumbitCancelButtons();
            form.submit();
        }
    });
    function isNumber(evt) 
    {
        evt = (evt) ? evt : window.event;
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            return false;
        }
        return true;
    }
    $('#Stripe').validate(
    {
        rules:
        {
            cardNumber:
            {
                required: true,
            },
            month:
            {
                required: true,
                maxlength: 2
            },
            year:
            {
                required: true,
                maxlength: 2
            },
            cvc:
            {
                required: true,
                maxlength: 4
            },
        },
        submitHandler: function(form, e)
        {
          e.preventDefault();
          confirmPayment();
        }
    });
    function makePayment()
    {
      var promiseObj = new Promise(function(resolve, reject)
      {
          var cardNumber = $("#cardNumber").val().trim();
          var month      = $("#month").val().trim();
          var year       = $("#year").val().trim();
          var cvc        = $("#cvc").val().trim();
          var currency   = $('#Stripe').find('input[name="currency"]').val().trim();
          var merchant   = $('#Stripe').find('input[name="merchant"]').val().trim();
          var amount     = {!! $paymentInfo['amount'] !!};
          
          $("#stripeError").html('');
          if (cardNumber && month && year && cvc) {
            $.ajax({
                type: "POST",
                url: SITE_URL + "/standard-merchant/stripe-make-payment",
                data:
                {
                  "_token":  '{{ csrf_token() }}',
                  'cardNumber': cardNumber,
                  'month': month,
                  'year': year,
                  'cvc': cvc,
                  'currency': currency,
                  'merchant': merchant,
                  'amount': amount,
                },
                dataType: "json",
                beforeSend: function (xhr) {
                    $(".standard-payment-submit-btn").attr("disabled", true);
                },
            }).done(function(response)
            {   
              if (response.data.status != 200) {
                  $("#stripeError").html(response.data.message);
                  $(".standard-payment-submit-btn").attr("disabled", true);
                  reject(response.data.status);
                  return false;    
              } else {
                  $(".standard-payment-submit-btn").attr("disabled", false);
                  resolve(response.data);
              }
            });
        }
      });
      return promiseObj;
    }
    function confirmPayment()
    {
        makePayment().then(function(result) {
          var form = $('#Stripe')[0];
          var formData = new FormData(form);
          formData.append('_token', "{{ csrf_token() }}");
          formData.append('paymentIntendId', result.paymentIntendId);
          formData.append('paymentMethodId', result.paymentMethodId);
          $.ajax({
              type: "POST",
              url: SITE_URL + "/payment/stripe",
              data: formData,
              processData: false,
              contentType: false,
              cache: false,
              beforeSend: function (xhr) {
                  $(".standard-payment-submit-btn").attr("disabled", true);
                  $(".fa-spin").show();
              },
          }).done(function(response)
          {   
              $(".fa-spin").hide();
              if (response.data.status != 200) {
                  $(".standard-payment-submit-btn").attr("disabled", true);
                  $("#stripeError").html(response.data.message);
                  return false;    
              } else {
                  window.location.replace(SITE_URL + '/payment/success');
              }
          });
        });
    }
    $("#month").change(function() { 
        makePayment();
    });
    $("#year, #cvc").on('keyup', $.debounce(1000, function() {
        makePayment();
    }));
    $("#cardNumber").on('keyup', $.debounce(1000, function() {
        makePayment();
    }));
    // For card number design
    document.getElementById('cardNumber').addEventListener('input', function (e) {
      var target = e.target, position = target.selectionEnd, length = target.value.length;
      target.value = target.value.replace(/[^\d]/g, '').replace(/(.{4})/g, '$1 ').trim();
      target.selectionEnd = position += ((target.value.charAt(position - 1) === ' ' && target.value.charAt(length - 1) === ' ' && length !== target.value.length) ? 1 : 0);
    });
  </script>
  <script src="https://www.paypal.com/sdk/js?client-id={{ isset($clientId) ? $clientId : '' }}&disable-funding=paylater&currency={{ isset($currencyCode) ? $currencyCode : '' }}"></script> 
  <script>
      paypal.Buttons({
          createOrder: function (data, actions) {
              // This function sets up the details of the transaction, including the amount and line item details.
              return actions.order.create({
                  purchase_units: [{
                      amount: {
                          value: "{!! $amount !!}"
                      }
                  }]
              });
          },
          onApprove: function (data, actions) {
              // This function captures the funds from the transaction.
              return actions.order.capture().then(function (details) {
                  // This function shows a transaction success message to your buyer.
                  // alert('Transaction completed by ' + details.payer.name.given_name);
                  $('#Paypal').append('<input type="hidden" name="amount" id="paypalAmount" />');
                  $('#Paypal').append('<input type="hidden" name="payment_id" id="payment_id" />');
                  $("#paypalAmount").val(btoa(details.purchase_units[0].amount.value));
                  $("#payment_id").val(btoa(details.id));
                  paypalSuccess();
              });
          }
      }).render('#paypal-button-container');
      function paypalSuccess()
      {
          var form = $('#Paypal')[0];
          var formData = new FormData(form);
          formData.append('_token', "{{ csrf_token() }}");
          $.ajax({
              type: "POST",
              url: SITE_URL + "/payment/paypal_payment_success",
              data: formData,
              processData: false,
              contentType: false,
              cache: false,
              beforeSend: function (xhr) {
                  $(".standard-payment-submit-btn").attr("disabled", true);
                  $(".fa-spin").show();
              },
          }).done(function(response) {
              $(".fa-spin").hide();
              window.location.replace(SITE_URL + response.data.redirectedUrl);
        });
      }
  </script>
   <script src="{{asset('public/user_dashboard/js/jquery.validate.min.js')}}" type="text/javascript"></script>
  <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
    integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
    crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"
    integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q"
    crossorigin="anonymous"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"
    integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl"
    crossorigin="anonymous"></script>
    </body>
</html>
