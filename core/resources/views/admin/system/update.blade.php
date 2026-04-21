@extends('admin.layouts.app')
@section('panel')
    <div class="row gy-4">
        <div class="col-lg-12">
            <div class="card border--primary">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                        <div>
                            <h4 class="mb-1">@lang('Current Application Version')</h4>
                            <p class="text-muted mb-0">{{ systemDetails()['name'] }} v{{ systemDetails()['version'] }}</p>
                        </div>
                        <span class="badge badge--success px-3 py-2">@lang('Manual Update Mode')</span>
                    </div>

                    <div class="alert alert-info mb-0">
                        <i class="las la-info-circle"></i>
                        @lang('Automatic vendor update checks and remote update downloads have been removed from this build. Apply future updates manually after taking a full backup of files and database.')
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
