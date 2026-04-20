@php
    $bannerContent = getContent('banner.content', true);
    $bannerElement = collect(getContent('banner.element', orderById: true));

    $backgroundImage = @$bannerContent->data_values->background_image
        ? getImage('assets/images/frontend/banner/' . @$bannerContent->data_values->background_image, '1920x800')
        : asset('assets/templates/parimatch/images/range-bg.png');

    $heroImage = @$bannerContent->data_values->image
        ? getImage('assets/images/frontend/banner/' . @$bannerContent->data_values->image, '670x675')
        : asset('assets/images/game/teen_patti.png');

    if ($bannerElement->isEmpty()) {
        $bannerElement = collect([
            (object) [
                'data_values' => (object) [
                    'title' => 'Play Teen Patti Live',
                    'subtitle' => 'Secure API + WebView integration ready for your app',
                    'button_url' => 'user/register',
                    'button_name' => 'Start Now',
                    'image' => null,
                ],
            ],
        ]);
    }
@endphp

<section class="banner-section bg-img" data-background-image="{{ $backgroundImage }}">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-lg-6 order-lg-1 order-2">
                <div class="banner-content-slider">
                    <div class="banner-content-slider__inner">
                        @foreach ($bannerElement as $banner)
                            <div class="banner-slider-item">
                                <div class="banner-slider-item__wrapper">
                                    <div class="banner-content">
                                        <h1 class="banner-content__title">{{ __(@$banner->data_values->title) }}</h1>
                                        <p class="banner-content__desc">{{ __(@$banner->data_values->subtitle) }}</p>
                                        <div class="banner-content__button">
                                            <a href="{{ @$banner->data_values->button_url ? url(@$banner->data_values->button_url) : 'javascript:void(0)' }}" class="btn btn--gradient">{{ __(@$banner->data_values->button_name) }}</a>
                                        </div>
                                    </div>
                                    <div class="banner-image">
                                        <img src="{{ @$banner->data_values->image ? getImage('assets/images/frontend/banner/' . @$banner->data_values->image, '185x215') : asset('assets/templates/parimatch/images/dealer.png') }}" alt="@lang('image')">
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="banner-slider-dots"></div>
            </div>
            <div class="col-lg-6 order-lg-2 order-1">
                <div class="banner-thumb">
                    <img src="{{ $heroImage }}" alt="@lang('image')">
                </div>
            </div>
        </div>
    </div>
</section>
