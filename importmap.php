<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.13',
    ],
    '@tailwindcss/forms' => [
        'version' => '0.5.10',
    ],
    'mini-svg-data-uri' => [
        'version' => '1.4.4',
    ],
    'picocolors' => [
        'version' => '1.1.1',
    ],
    'bootstrap' => [
        'version' => '5.3.6',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.6',
        'type' => 'css',
    ],
    'aos' => [
        'version' => '2.3.4',
    ],
    'glightbox' => [
        'version' => '3.3.1',
    ],
    'swiper/swiper-bundle' => [
        'version' => '11.2.6',
    ],
    'imagesloaded' => [
        'version' => '5.0.0',
    ],
    'ev-emitter' => [
        'version' => '2.1.2',
    ],
    'isotope-layout' => [
        'version' => '3.0.6',
    ],
    'outlayer' => [
        'version' => '2.1.1',
    ],
    'get-size' => [
        'version' => '3.0.0',
    ],
    'desandro-matches-selector' => [
        'version' => '2.0.2',
    ],
    'fizzy-ui-utils' => [
        'version' => '3.0.0',
    ],
    'masonry-layout' => [
        'version' => '4.2.2',
    ],
    '@srexi/purecounterjs' => [
        'version' => '1.5.0',
    ],
    'apexcharts' => [
        'version' => '4.7.0',
    ],
    'bootstrap-icons/font/bootstrap-icons.min.css' => [
        'version' => '1.13.1',
        'type' => 'css',
    ],
    'boxicons' => [
        'version' => '2.1.4',
    ],
    'boxicons/css/boxicons.min.css' => [
        'version' => '2.1.4',
        'type' => 'css',
    ],
    'echarts' => [
        'version' => '5.6.0',
    ],
    'tslib' => [
        'version' => '2.8.1',
    ],
    'zrender/lib/zrender.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/util.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/env.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/timsort.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/Eventful.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/Text.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/tool/color.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/Path.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/tool/path.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/matrix.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/vector.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/Transformable.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/Image.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/Group.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Circle.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Ellipse.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Sector.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Ring.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Polygon.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Polyline.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Rect.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Line.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/BezierCurve.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/shape/Arc.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/CompoundPath.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/LinearGradient.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/RadialGradient.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/BoundingRect.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/OrientedBoundingRect.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/Point.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/IncrementalDisplayable.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/helper/subPixelOptimize.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/dom.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/helper/parseText.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/WeakMap.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/LRU.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/contain/text.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/canvas/graphic.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/platform.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/contain/polygon.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/PathProxy.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/contain/util.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/curve.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/svg/Painter.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/canvas/Painter.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/event.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/tool/parseSVG.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/tool/parseXML.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/graphic/Displayable.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/core/bbox.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/contain/line.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/contain/quadratic.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/animation/Animator.js' => [
        'version' => '5.6.1',
    ],
    'zrender/lib/tool/morphPath.js' => [
        'version' => '5.6.1',
    ],
    'quill' => [
        'version' => '2.0.3',
    ],
    'lodash-es' => [
        'version' => '4.17.21',
    ],
    'parchment' => [
        'version' => '3.0.0',
    ],
    'quill-delta' => [
        'version' => '5.1.0',
    ],
    'eventemitter3' => [
        'version' => '5.0.1',
    ],
    'fast-diff' => [
        'version' => '1.3.0',
    ],
    'lodash.clonedeep' => [
        'version' => '4.5.0',
    ],
    'lodash.isequal' => [
        'version' => '4.5.0',
    ],
    'remixicon/fonts/remixicon.min.css' => [
        'version' => '4.6.0',
        'type' => 'css',
    ],
    'simple-datatables' => [
        'version' => '10.0.0',
    ],
    'tinymce' => [
        'version' => '7.9.0',
    ],
    'simple-datatables/dist/style.min.css' => [
        'version' => '10.0.0',
        'type' => 'css',
    ],
    'intl-tel-input/build/css/intlTelInput.min.css' => [
        'version' => '25.3.1',
        'type' => 'css',
    ],
    'intl-tel-input/build/css/intlTelInput.css' => [
        'version' => '25.3.1',
        'type' => 'css',
    ],
    'intl-tel-input/build/js/intlTelInput.min.js' => [
        'version' => '25.3.1',
    ],
    'intl-tel-input/build/js/utils.js' => [
        'version' => '25.3.1',
    ],
    'tom-select' => [
        'version' => '2.4.3',
    ],
    'tom-select/dist/css/tom-select.bootstrap5.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
    '@stimulus-components/color-picker' => [
        'version' => '2.0.0',
    ],
    '@simonwep/pickr' => [
        'version' => '1.9.1',
    ],
    'tailwindcss/plugin' => [
        'version' => '3.4.17',
    ],
    'tailwindcss/defaultTheme' => [
        'version' => '3.4.17',
    ],
    'tailwindcss/colors' => [
        'version' => '3.4.17',
    ],
    '@orchidjs/sifter' => [
        'version' => '1.1.0',
    ],
    '@orchidjs/unicode-variants' => [
        'version' => '1.1.2',
    ],
    'tom-select/dist/css/tom-select.default.min.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
];
