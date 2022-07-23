@extends('user_dashboard.layouts.app')

@section('content')
    <section class="section-06 history padding-30">
        <div class="container">
            <div class="row">

                <div class="col-md-7 col-xs-12 mb20 marginTopPlus">
                    @include('user_dashboard.layouts.common.alert')
                    <form id="depositForm1" action="{{ url('payout/confirmer') }}" method="post" accept-charset='UTF-8'>
                        <div class="card">
                            <div class="card-header">
                                <div class="chart-list float-left">
                                    <ul>
                                        <li class="">Vérification</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="wap-wed mt20 mb20">
                                <input type="hidden" value="{{csrf_token()}}" name="_token" id="token">
                                <input type="hidden" name="percentage_fee" id="percentage_fee" class="form-control"
                                       value="">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="exampleInputPassword1">SMS de vérification</label>
                                                <input type="text" class="form-control amount" name="otp" placeholder="Saisir le code de vérification" type="text" id="otp">
                                            </div>
                                        </div>
                                       
                                       
                                       
                                    </div>
                                </div>
                                <div class="" style="margin-bottom: 10px">
                                    <button type="submit" class="btn btn-cust col-12 transfer_form" id="deposit-money">
                                        <i class="spinner fa fa-spinner fa-spin" style="display: none;"></i> <span id="deposit-money-text" style="font-weight: bolder;">Confirmer</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <!--/col-->
            </div>
            <!--/row-->
        </div>
    </section>
    @include('user_dashboard.layouts.common.help')
@endsection

@section('js')

<script src="{{asset('public/user_dashboard/js/jquery.validate.min.js')}}" type="text/javascript"></script>
<script src="{{asset('public/user_dashboard/js/additional-methods.min.js')}}" type="text/javascript"></script>
<script src="{{asset('public/user_dashboard/js/sweetalert/sweetalert-unpkg.min.js')}}" type="text/javascript"></script>

<script>
  
$(document).ready(function() {
    $("#deposit-money").click(function() {
      // disable button
      $(this).prop("disabled", true);
      // add spinner to button
      $(this).html(
        `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Chargement...`
      );
      $("#depositForm1" ).submit();
    });
});
</script>

@endsection