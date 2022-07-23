@extends('user_dashboard.layouts.app')

@section('content')
    <section class="min-vh-100">
    <div class="my-30">
        <div class="container-fluid">
            <!-- Page title start -->
            <div>
                <h3 class="page-title">{{ __('Confirmation') }}</h3>
            </div>
            <!-- Page title end-->

            <div class="row mt-4">
                <div class="col-lg-4">
                    <div class="mt-5">
                        <h3 class="sub-title">{{ __('Vérification par SMS') }}</h3>
                        <p class="text-gray-500 text-16 text-justify">{{ __('Merci de vérifier et saisir le code que vous avez reçu.') }}</p>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="row">
                        <div class="col-lg-10">
                            <div class="d-flex w-100 mt-4">
                                <ol class="breadcrumb w-100">
                                    <li class="breadcrumb-first text-white">{{ __('Create') }}</li>
                                    <li>{{ __('Confirmation') }}</li>
                                    <li class="active">{{ __('Success') }}</li>
                                </ol>
                            </div>

                            <div class="bg-secondary rounded p-35 mt-5 shadow">
                                @include('user_dashboard.layouts.common.alert')
                               <form id="depositForm1" action="{{ url('deposit/confirmer') }}" method="post" accept-charset='UTF-8'>
                                    <div>
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
                                                <button type="submit" class="btn btn-primary col-12 transfer_form" id="deposit-money">
                                                    <i class="spinner fa fa-spinner fa-spin" style="display: none;"></i> <span id="deposit-money-text" style="font-weight: bolder;">Confirmer</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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