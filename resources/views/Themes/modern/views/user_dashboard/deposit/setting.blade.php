@extends('user_dashboard.layouts.app')

@section('content')
    <section class="min-vh-100">
        <div class="mt-30">
            <div class="container-fluid">
                <!-- Page title start -->
                <div class="d-flex justify-content-between">
                    <div>
                        <h3 class="page-title">{{ __('Deposit') }}</h3>
                    </div>

                    <div>
                        <a href="{{ url('/payout') }}">
                            <button class="btn btn-primary px-4 py-2" data-toggle="modal" data-target="#addModal" id="addBtn">
                                <i class="fa fa-plus"></i> @lang('message.dashboard.payout.payout-setting.add-setting')
                            </button>
                        </a>
                    </div>
                </div>

                <!-- Page title end-->
                <div class="mt-4 border-bottom">
                    <div class="d-flex flex-wrap justify-content-between">
                        <div>
                            <div class="d-flex flex-wrap">
                                <a href="{{url('/deposit')}}">
                                    <div class="mr-4 pb-3">
                                        <p class="text-16 font-weight-400 text-gray-500">{{ __('Liste de de dépôt') }}</p>
                                    </div>
                                </a>

                                <a href="{{url('/deposit/setting')}}">
                                    <div class="mr-4 border-bottom-active pb-3">
                                        <p class="text-16 font-weight-600 text-active">{{ __('Paramêtres de dépôt') }}  </p>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-12">
                                @include('user_dashboard.layouts.common.alert')
                                <div class="bg-secondary mt-3 shadow">
                                    <div class="table-responsive">
                                        @if($payoutSettings->count() > 0)
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th class="pl-5">Type de dépôt</th>
                                                        <th>@lang('message.dashboard.payout.payout-setting.account')</th>
                                                        <th class="pr-5 text-center">Use It</th>
                                                        <th class="pr-5 text-right">@lang('message.dashboard.payout.payout-setting.action')</th>

                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach($payoutSettings as $row)
                                                        <tr class="row_id_{{$row->id}}">
                                                            <td class="pl-5">
                                                                <p class="">{{$row->paymentMethod->name}}</p>
                                                            </td>

                                                            <td>
                                                                @if($row->paymentMethod->id == 3)
                                                                    {{$row->email }}
                                                                @else
                                                                    {{$row->account_name}} (*****{{substr($row->account_number,-4)}}
                                                                    )<br/>
                                                                    {{$row->bank_name}}
                                                                @endif
                                                            </td>
                                                            <td class="pl-5">
                                                                <form action="{{ url('deposit/stb-deposit')}}" method="post">
                                                                    @csrf
                                                                    <input type="hidden" name="currency_id" value="{{$row->id}}">                                                                    
                                                                    <input type="hidden" name="payout_setting_id" value="{{$row->id}}">
                                                                    <button type="submit" class="btn btn-sm btn-light mr-lg-2 mt-2 edit-setting" >
                                                                        <svg id="ej0XF8uPx911" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" class="sidebaricon" stroke="currentColor" viewBox="0 -20 512 512" shape-rendering="geometricPrecision" text-rendering="geometricPrecision">
                                                                            <path id="ej0XF8uPx912"  stroke-width="2" d="M478.609000,225.480000L478.609000,172.521000C478.609000,144.903000,456.140000,122.434000,428.522000,122.434000L363.913000,122.434000L255.962000,26.478000C249.637000,20.855000,240.103000,20.855000,233.778000,26.478000L125.826000,122.435000L83.478000,122.435000C37.448000,122.435000,0,159.883000,0,205.913000L0,406.261000C0,452.291000,37.448000,489.739000,83.478000,489.739000L428.521000,489.739000C456.139000,489.739000,478.608000,467.270000,478.608000,439.652000L478.608000,386.693000C498.041000,379.801000,511.999000,361.243000,511.999000,339.478000L511.999000,272.695000C512,250.930000,498.041000,232.372000,478.609000,225.480000ZM244.870000,61.294000L313.653000,122.435000L176.087000,122.435000L244.870000,61.294000ZM445.217000,439.652000C445.217000,448.858000,437.727000,456.348000,428.521000,456.348000L83.478000,456.348000C55.860000,456.348000,33.391000,433.879000,33.391000,406.261000L33.391000,205.913000C33.391000,178.295000,55.860000,155.826000,83.478000,155.826000L428.521000,155.826000C437.727000,155.826000,445.217000,163.316000,445.217000,172.522000L445.217000,222.609000L395.130000,222.609000C349.100000,222.609000,311.652000,260.057000,311.652000,306.087000C311.652000,352.117000,349.100000,389.565000,395.130000,389.565000L445.217000,389.565000L445.217000,439.652000ZM478.609000,339.478000C478.609000,348.684000,471.119000,356.174000,461.913000,356.174000L395.130000,356.174000C367.512000,356.174000,345.043000,333.705000,345.043000,306.087000C345.043000,278.469000,367.512000,256,395.130000,256L461.913000,256C471.119000,256,478.609000,263.490000,478.609000,272.696000L478.609000,339.478000Z"/>
                                                                            <circle id="ej0XF8uPx913" stroke-width="2" r="16.696000" transform="matrix(1 0 0 1 395.13000000000000 306.08699999999999)"/>
                                                                        </svg>                                                                    
                                                                    </button>                  
                                                                </form>                                        
                                                            </td>
                                                            <td class="pr-5 text-right">
                                                                
                                                                <!-- <a data-id="{{$row->id}}" data-type="{{$row->type}}" data-obj="{{json_encode($row->getAttributes())}}" class="btn btn-sm btn-light mr-lg-2 mt-2 edit-setting"><i class="far fa-edit"></i></a> -->
                                                                <button class="btn btn-sm btn-light mr-lg-2 mt-2 edit-setting" data-id="{{$row->id}}" data-toggle="modal" data-target="#addModal" data-type="{{$row->type}}" data-obj="{{json_encode($row->getAttributes())}}" id="editBtn">
                                                                    <i class="far fa-edit"></i>
                                                                </button>
                                                                <form action="{{url('deposit/setting/delete')}}" method="post" style="display: inline">
                                                                    @csrf
                                                                    <input type="hidden" name="id" value="{{$row->id}}">
                                                                    <a class="btn btn-sm btn-light mt-2 delete-setting" data-toggle="modal" data-target="#delete-warning-modal" data-title="{{__("Delete Data")}}"
                                                                    data-message="{{__("Are you sure you want to delete this Data ?")}}" data-row="{{$row->id}}" href=""><i class="fa fa-trash"></i></a>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @else
                                            <div class="p-5 text-center">
                                                <svg width="96" height="96" fill="none" class="mx-auto mb-6 text-gray-900"><path d="M36 28.024A18.05 18.05 0 0025.022 39M59.999 28.024A18.05 18.05 0 0170.975 39" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><ellipse cx="37.5" cy="43.5" rx="4.5" ry="7.5" fill="currentColor"></ellipse><ellipse cx="58.5" cy="43.5" rx="4.5" ry="7.5" fill="currentColor"></ellipse><path d="M24.673 75.42a9.003 9.003 0 008.879 5.563m-8.88-5.562A8.973 8.973 0 0124 72c0-7.97 9-18 9-18s9 10.03 9 18a9 9 0 01-8.448 8.983m-8.88-5.562C16.919 68.817 12 58.983 12 48c0-19.882 16.118-36 36-36s36 16.118 36 36-16.118 36-36 36a35.877 35.877 0 01-14.448-3.017" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path><path d="M41.997 71.75A14.94 14.94 0 0148 70.5c2.399 0 4.658.56 6.661 1.556a3 3 0 003.999-4.066 12 12 0 00-10.662-6.49 11.955 11.955 0 00-7.974 3.032c1.11 2.37 1.917 4.876 1.972 7.217z" fill="currentColor"></path></svg>
                                                <p>{{ __('Sorry!') }} @lang('message.dashboard.payout.list.not-found')</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4">
                                    {{ $payoutSettings->links('vendor.pagination.bootstrap-4') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- addModal Modal-->
    <div class="modal fade" id="addModal" role="dialog">
        <div class="modal-dialog modal-lg">

            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header" style="display: block;">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Paramètre de compte STB</h4>
                </div>
                <div class="modal-body">
                    <form id="payoutSettingForm" method="post">
                        {{csrf_field()}}
                        <div id="settingId"></div>
                        <!--<div class="col-md-10">
                            <div class="form-group">
                                <label>@lang('message.dashboard.payout.payout-setting.payout-type')</label>
                                <select name="type" id="type" class="form-control" >
                                    @foreach($paymentMethods as $method)
                                        @if($method->name == "CompteStb")
                                        <option value="{{$method->id}}" selected>{{$method->name}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>-->
                        <div id="bankForm">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Nom du titulaire du compte  *</label>
                                    <input name="account_name" class="form-control" value="{{ $row->account_name }}">

                                </div>
                                <div class="form-group">
                                    <label>RIB *</label>
                                    <input name="account_number" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')" value="{{ $row->account_number }}">

                                </div>
                                <div class="form-group">
                                    <label>CIN *</label>
                                    <input name="swift_code" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')" value="{{ $row->swift_code }}">
                                </div>
                                <!--<div class="form-group">
                                    <label>@lang('message.dashboard.payout.payout-setting.modal.bank-name') *</label>
                                    <input name="bank_name" class="form-control">
                                </div>-->
                            </div>
                            <div class="col-md-6">
                                <!--<div class="form-group">
                                    <label>@lang('message.dashboard.payout.payout-setting.modal.branch-name') *</label>
                                    <input name="branch_name" class="form-control">
                                </div>-->
                                <div class="form-group">
                                    <label>@lang('message.dashboard.payout.payout-setting.modal.branch-city')</label>
                                    <input name="branch_city" class="form-control" value="{{ $row->bank_branch_city }}">
                                </div>
                                <div class="form-group">
                                    <label>@lang('message.dashboard.payout.payout-setting.modal.branch-address')</label>
                                    <input name="branch_address" class="form-control" value="{{ $row->bank_branch_address }}">
                                </div>
                                <!--<div class="form-group">
                                    <label>@lang('message.dashboard.payout.payout-setting.modal.country') *</label>
                                    <select name="country" class="form-control">
                                        @foreach($countries as $country)
                                            <option value="{{$country->id}}">{{$country->name}}</option>
                                        @endforeach
                                    </select>
                                </div>-->
                            </div>

                        </div>
                        <div id="paypalForm" style="margin:0 auto;display: none">
                            <div class="col-md-10">
                                <div class="form-group">
                                    <label>@lang('message.dashboard.payout.payout-setting.modal.email')</label>
                                    <input name="email" class="form-control" onkeyup="this.value = this.value.replace(/\s/g, '')">
                                </div>
                            </div>
                        </div>

                        <div class="card-footer" style="background-color: inherit;border: 0">
                            <div class="row m-0">
                                <div class="col-md-12 pb-2">
                                    <button type="submit" class="btn btn-primary px-4 py-2" id="submit_btn">
                                        <i class="spinner fa fa-spinner fa-spin" style="display: none;"></i> <span id="submit_text">@lang('message.form.submit')</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">@lang('message.form.close')</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')

    <script src="{{asset('public/user_dashboard/js/jquery.validate.min.js')}}" type="text/javascript"></script>
    <script src="{{asset('public/user_dashboard/js/additional-methods.min.js')}}" type="text/javascript"></script>

    @include('user_dashboard.layouts.common.check-user-status')

    <script>
        //Clear validation errors on modal close - starts
        $(document).ready(function() {
            $('#addModal').on('hidden.bs.modal', function (e) {
                $('#payoutSettingForm').validate().resetForm();
                $('#payoutSettingForm').find('.error').removeClass('error');
            });
        });
        //Clear validation errors on modal close - ends
        $(document).ready(function(){
            /*$('#bankForm').hide();
            $('#paypalForm').css('display', 'flex');*/
            $('#bankForm').css('display', 'flex');
            $('#paypalForm').hide();
        });
        $('#type').on('change', function()
        {
            if ($('option:selected', this).text() == 'Paypal')
            {
                $('#bankForm').hide();
                $('#paypalForm').css('display', 'flex');
            }
            else if ($('option:selected', this).text() == 'Bank')
            {
                $('#bankForm').css('display', 'flex');
                $('#paypalForm').hide();
            }
        });
        $('#addBtn').on('click', function(e)
        {
            e.preventDefault();
            //if user is suspended
            checkUserSuspended(e);
            //if user is not suspended
            $('#settingId').html('');
            var form = $('#payoutSettingForm');
            form.attr('action', '{{url('deposit/setting/store')}}');
            $.each(form[0].elements, function(index, elem)
            {
                if (elem.name != "_token" && elem.name != "setting_id")
                {
                    $(this).val("");
                    if (elem.name == "type")
                    {
                        $(this).val(1).change().removeAttr('disabled');
                    }
                }
            });
        });
        jQuery.extend(jQuery.validator.messages,
        {
            required: "{{__('This field is required.')}}",
        })
        $('#payoutSettingForm').validate(
        {
            rules:
            {
                type:
                {
                    required: true
                },
                account_name:
                {
                    required: true
                },
                account_number:
                {
                    required: true
                },
                swift_code:
                {
                    required: true
                },
               /* bank_name:
                {
                    required: true
                },*/
               /* branch_name:
                {
                    required: true
                },*/
                branch_city:
                {
                    required: false
                },
                branch_address:
                {
                    required: false,
                },
                email:
                {
                    required: true,
                    email: true
                },
               /* country:
                {
                    required: true
                },*/
            },
            submitHandler: function(form)
            {
                $("#submit_btn").attr("disabled", true);
                $(".spinner").show();
                $("#submit_text").text("{{__('Submitting...')}}");
                form.submit();
            }
        });
        $('#editBtn').on('click', function(e)
        {
            e.preventDefault();
            checkUserSuspended(e);
            // return false;
            //if user is not suspended
            var obj = JSON.parse($(this).attr('data-obj'));
            var settingId = $(this).attr('data-id');
            var form = $('#payoutSettingForm');
            form.attr('action', '{{url('deposit/setting/update')}}');
            form.attr('method', 'post');
            var html = '<input type="hidden" name="setting_id" value="' + settingId + '">';
            $('#settingId').html(html);
            if (obj.type == 6)
            {
                $.each(form[0].elements, function(index, elem)
                {
                    switch (elem.name)
                    {
                        case "type":
                            $(this).val(obj.type).change().attr('disabled', 'true');
                            break;
                        case "account_name":
                            $(this).val(obj.account_name);
                            break;
                        case "account_number":
                            $(this).val(obj.account_number);
                            break;
                        case "branch_address":
                            $(this).val(obj.bank_branch_address);
                            break;
                        case "branch_city":
                            $(this).val(obj.bank_branch_city);
                            break;
                        /*case "branch_name":
                            $(this).val(obj.bank_branch_name);
                            break;*/
                       /* case "bank_name":
                            $(this).val(obj.bank_name);
                            break;*/
                        /*case "country":
                            $(this).val(obj.country);
                            break;*/
                        case "swift_code":
                            $(this).val(obj.swift_code);
                            break;
                        default:
                            break;
                    }
                })
            }
            else if (obj.type == 3)
            {
                $.each(form[0].elements, function(index, elem)
                {
                    if (elem.name == 'email')
                    {
                        $(this).val(obj.email);
                    }
                    else if (elem.name == 'type')
                    {
                        $(this).val(obj.type).change().attr('disabled', 'true');
                    }
                })
            }
            setTimeout(()=>{
                $('#addModal').modal();
            }, 400)
            // $('#addModal').modal();
        });
        $('.delete-setting').on('click', function(e)
        {
            e.preventDefault();
            checkUserSuspended(e);
        });
    </script>
@endsection