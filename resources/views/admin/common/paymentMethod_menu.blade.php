<div class="box box-primary">

  <div class="box-header with-border">
    <h3 class="box-title underline">Payment Methods</h3>
  </div>
  <div class="box-body no-padding" style="display: block;">
    <ul class="nav nav-pills nav-stacked">

      {{-- <li  {{ isset($list_menu) &&  $list_menu == 'mts' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/mts/'.$currency->id) }}'>Mts</a>
      </li> --}}

      <li {{ isset($list_menu) &&  $list_menu == 'stripe' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/stripe/'.$currency->id) }}'>Stripe</a>
      </li>

      <li  {{ isset($list_menu) &&  $list_menu == 'clictopay' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url('admin/settings/payment-methods/clictopay/'.$currency->id) }}'>Clictopay</a>
      </li>

      <li  {{ isset($list_menu) &&  $list_menu == 'paypal' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/paypal/'.$currency->id) }}'>PayPal</a>
      </li>

      <li {{ isset($list_menu) &&  $list_menu == 'twoCheckout' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/twoCheckout/'.$currency->id) }}'>2Checkout</a>
      </li>

      <li {{ isset($list_menu) &&  $list_menu == 'payUMoney' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/payUMoney/'.$currency->id) }}'>PayUMoney</a>
      </li>

      <li {{ isset($list_menu) &&  $list_menu == 'coinPayments' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/coinPayments/'.$currency->id) }}'>CoinPayments</a>
      </li>

      <li {{ isset($list_menu) &&  $list_menu == 'Payeer' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/Payeer/'.$currency->id) }}'>Payeer</a>
      </li>


      <li {{ isset($list_menu) &&  $list_menu == 'bank' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/bank/'.$currency->id) }}'>Banks</a>
      </li>


      <li {{ isset($list_menu) &&  $list_menu == 'CompteStb' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/CompteStb/'.$currency->id) }}'>Compte STB</a>
      </li>

      <li {{ isset($list_menu) &&  $list_menu == 'CarteStb' ? 'class=active' : ''}} >
        <a data-spinner="true" href='{{ url(\Config::get('adminPrefix').'/settings/payment-methods/CarteStb/'.$currency->id) }}'>Carte STB</a>
      </li>

    </ul>
  </div>
</div>

