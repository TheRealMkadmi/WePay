<!DOCTYPE html>
<html lang="en">
<head>
    <title>@lang('message.express-payment-form.merchant-payment')</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
          integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="{{asset('public/frontend/css/styles.css')}}">

    <script src="{{ asset('public/backend/jquery/dist/jquery.js') }}"></script>
    <script src="{{ asset('public/backend/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <script type="text/javascript">
        var SITE_URL = "{{URL::to('/')}}";
    </script>
    <style>
    </style>
</head>
<body>
<div class="container ">


    <div class="row justify-content-center">
        <div class="col-md-6 card">

            <div class="row">

                <div class="col-12 col-md-12 d-flex justify-content-between align-items-start  m-2">
                    <div class="d-flex flex-column align-items-start">
                        <h5 class="document-type display-4 text-small">Nom et prénom : {{$first_name}} </h5>
                        <p class="text-right display-4 text-small">Marchand : {{$business_name}}</p>
                        @if($wallets->count()>0)

                            @foreach($wallets as $wallet)
                                @php
                                    $walletCurrencyCode = encrypt(strtolower($wallet->currency->code));
                                    $walletId = encrypt($wallet->id);
                                @endphp
                                @if($wallet->balance >= 0)

                                    @if ($wallet->currency->type == 'fiat')
                                            @if ( $wallet->currency->code == "TND")
                                                <td class="display-4 text-small"> Solde wallet
                                                    : {{ '+'.formatNumber($wallet->balance) }} {{ $wallet->currency->code }}</td>
                                            @endif
                                        @if ($wallet->currency->type != 'fiat')
                                            <td class="display-4 text-small"> Solde wallet
                                                : {{ '+'.formatNumber($wallet->balance) }} {{ $wallet->currency->code }}</td>
                                        @endif
                                    @endif
                                @else
                                    @lang('message.dashboard.right-table.no-wallet')
                                @endif

                            @endforeach

                        @endif

                    </div>


                </div>

            </div>

            <table class="table table-striped">
                <thead>
                <tr>
                    <th>N°</th>
                    <th>Produit</th>
                    <th>Prix</th>
                </tr>
                </thead>

                <tbody>
                @foreach($wallets as $wallet)
                    @php
                        $walletCurrencyCode = encrypt(strtolower($wallet->currency->code));
                        $walletId = encrypt($wallet->id);
                    @endphp
                   

                @endforeach
 @if($wallet->balance >= 0)

                        <tr>
                            <td>{{$order_no}}</td>
                            <td> {{$item_name}}</td>
                            <td>{{$amount}}</td>
                        </tr>
                    @endif

                </tbody>
            </table>
               <form action="{{url('payment/mts_pay')}}" method="POST" accept-charset="utf-8">
                        {{csrf_field()}}
                         <input name="merchant" value="{{$merchant}}" type="hidden">
                        <input name="currency" value="{{ $currency}}" type="hidden">
                        <input name="order_no" value="{{$order_no}}" type="hidden">
                        <input name="amount" value="{{ $amount }}" type="hidden">
                        <input name="item_name" value="{{$item_name}}" type="hidden">
                        <div class="d-flex justify-content-center">
                         <button type="submit" class="btn btn-sm btn-info col-md-6">
                                Payer
                            </button>
                        </div>
    </form>

        </div>
    </div>

</div>
<style>
</style>

</body>
</html>