<?php

namespace WPSocialReviews\App\Services\Includes;

class CountryNames
{
    /**
     * Get all available languages/locales
     * @return array
     */
    public function get()
    {
        return $this->languageList();
    }

     /**
     * Get all available countries
     * @return array
     */
    public function getCountries()
    {
        return $this->countryList();
    }

   /**
     * Get language data by code
     * @param string $code
     * @return array|null
     */
    public function getLanguageByCode($code)
    {
        $languages = $this->languageList();
        return isset($languages[$code]) ? $languages[$code] : null;
    }

     /**
     * Get country data by code
     * @param string $code
     * @return array|null
     */
    public function getCountryByCode($code)
    {
        $countries = $this->countryList();
        return isset($countries[$code]) ? $countries[$code] : null;
    }

    /**
     * Get all language data
     * @return array
     */
    private function languageList()
    {
        return array(
            '' => array(
                'label'    => 'Choose a language',
            ),
            'all' => array(
                'code'     => 'all',
                'value'    => 'all',
                'label'    => 'All',
                'facebook' => 'all',
            ),
            'af' => array(
                'code'     => 'af',
                'value'    => 'af',
                'label'    => 'Afrikaans - af',
                'facebook' => 'af_ZA',
            ),
            'am' => array(
                'code'     => 'am',
                'value'    => 'am',
                'label'    => 'አማርኛ - am',
                'facebook' => 'am_ET',
            ),
            'ar' => array(
                'code'     => 'ar',
                'value'    => 'ar',
                'label'    => 'العربية - ar',
                'facebook' => 'ar_AR',
            ),
            'arg' => array(
                'code'     => 'an',
                'value'    => 'arg',
                'label'    => 'Aragonés - arg',
            ),
            'ary' => array(
                'code'     => 'ar',
                'value'    => 'ary',
                'label'    => 'العربية المغربية - ary',
                'facebook' => 'ar_AR',
            ),
            'as' => array(
                'code'     => 'as',
                'value'    => 'as',
                'label'    => 'অসমীয়া - as',
                'facebook' => 'as_IN',
            ),
            'az' => array(
                'code'     => 'az',
                'value'    => 'az',
                'label'    => 'Azərbaycan - az',
                'facebook' => 'az_AZ',
            ),
            'azb' => array(
                'code'     => 'az',
                'value'    => 'azb',
                'label'    => 'گؤنئی آذربایجان - azb',
            ),
            'bel' => array(
                'code'     => 'be',
                'value'    => 'bel',
                'label'    => 'Беларуская мова - bel',
                'facebook' => 'be_BY',
            ),
            'bg_BG' => array(
                'code'     => 'bg',
                'value'    => 'bg_BG',
                'label'    => 'български - bg_BG',
                'facebook' => 'bg_BG',
            ),
            'bn_BD' => array(
                'code'     => 'bn',
                'value'    => 'bn_BD',
                'label'    => 'বাংলা - bn_BD',
                'facebook' => 'bn_IN',
            ),
            'bo' => array(
                'code'     => 'bo',
                'value'    => 'bo',
                'label'    => 'བོད་ཡིག - bo',
            ),
            'bs_BA' => array(
                'code'     => 'bs',
                'value'    => 'bs_BA',
                'label'    => 'Bosanski - bs_BA',
                'facebook' => 'bs_BA',
            ),
            'ca' => array(
                'code'     => 'ca',
                'value'    => 'ca',
                'label'    => 'Català - ca',
                'facebook' => 'ca_ES',
            ),
            'ceb' => array(
                'code'     => 'ceb',
                'value'    => 'ceb',
                'label'    => 'Cebuano - ceb',
                'facebook' => 'cx_PH',
            ),
            'ckb' => array(
                'code'     => 'ku',
                'value'    => 'ckb',
                'label'    => 'کوردی - ckb',
                'facebook' => 'cb_IQ',
            ),
            'cs_CZ' => array(
                'code'     => 'cs',
                'value'    => 'cs_CZ',
                'label'    => 'Čeština - cs_CZ',
                'facebook' => 'cs_CZ',
            ),
            'cy' => array(
                'code'     => 'cy',
                'value'    => 'cy',
                'label'    => 'Cymraeg - cy',
                'facebook' => 'cy_GB',
            ),
            'da' => array(
                'code'     => 'da',
                'value'    => 'da',
                'label'    => 'Da',
                'facebook' => 'da',
            ),
            'da_DK' => array(
                'code'     => 'da',
                'value'    => 'da_DK',
                'label'    => 'Dansk - da_DK',
                'facebook' => 'da_DK',
            ),
            'de' => array(
                'code'     => 'de',
                'value'    => 'de',
                'label'    => 'Deutsch - de',
                'facebook' => 'de',
            ),
            'de_AT' => array(
                'code'     => 'de',
                'value'    => 'de_AT',
                'label'    => 'Deutsch - de_AT',
                'facebook' => 'de_DE',
            ),
            'de_CH' => array(
                'code'     => 'de',
                'value'    => 'de_CH',
                'label'    => 'Deutsch - de_CH',
                'facebook' => 'de_DE',
            ),
            'de_CH_informal' => array(
                'code'     => 'de',
                'value'    => 'de_CH_informal',
                'label'    => 'Deutsch - de_CH_informal',
                'facebook' => 'de_DE',
            ),
            'de_DE' => array(
                'code'     => 'de',
                'value'    => 'de_DE',
                'label'    => 'Deutsch - de_DE',
                'facebook' => 'de_DE',
            ),
            'de_DE_formal' => array(
                'code'     => 'de',
                'value'    => 'de_DE_formal',
                'label'    => 'Deutsch - de_DE_formal',
                'facebook' => 'de_DE',
            ),
            'dsb' => array(
                'code'     => 'dsb',
                'value'    => 'dsb',
                'label'    => 'Dolnoserbšćina - dsb',
            ),
            'dzo' => array(
                'code'     => 'dz',
                'value'    => 'dzo',
                'label'    => 'རྫོང་ཁ - dzo',
            ),
            'el' => array(
                'code'     => 'el',
                'value'    => 'el',
                'label'    => 'Ελληνικά - el',
                'facebook' => 'el_GR',
            ),
            'en' => array(
                'code'     => 'en',
                'value'    => 'en',
                'label'    => 'English - en',
                'facebook' => 'en',
            ),
            'en_AU' => array(
                'code'     => 'en',
                'value'    => 'en_AU',
                'label'    => 'English - en_AU',
                'facebook' => 'en_US',
            ),
            'en_CA' => array(
                'code'     => 'en',
                'value'    => 'en_CA',
                'label'    => 'English - en_CA',
                'facebook' => 'en_US',
            ),
            'en_GB' => array(
                'code'     => 'en',
                'value'    => 'en_GB',
                'label'    => 'English - en_GB',
                'facebook' => 'en_GB',
            ),
            'en_NZ' => array(
                'code'     => 'en',
                'value'    => 'en_NZ',
                'label'    => 'English - en_NZ',
                'facebook' => 'en_US',
            ),
            'en_US' => array(
                'code'     => 'en',
                'value'    => 'en_US',
                'label'    => 'English - en_US',
                'facebook' => 'en_US',
            ),
            'en_ZA' => array(
                'code'     => 'en',
                'value'    => 'en_ZA',
                'label'    => 'English - en_ZA',
                'facebook' => 'en_US',
            ),
            'en_HK' => array(
                'code'     => 'en',
                'value'    => 'en_HK',
                'label'    => 'English - en_HK',
                'facebook' => 'en_HK',
            ),
            'en_IN' => array(
                'code'     => 'en',
                'value'    => 'en_IN',
                'label'    => 'English - en_IN',
                'facebook' => 'en_IN',
            ),
            'en_IE' => array(
                'code'     => 'en',
                'value'    => 'en_IE',
                'label'    => 'English - en_IE',
                'facebook' => 'en_IE',
            ),
            'en_MY' => array(
                'code'     => 'en',
                'value'    => 'en_MY',
                'label'    => 'English - en_MY',
                'facebook' => 'en_MY',
            ),
            'en_PH' => array(
                'code'     => 'en',
                'value'    => 'en_PH',
                'label'    => 'English - en_PH',
                'facebook' => 'en_PH',
            ),
            'en_SG' => array(
                'code'     => 'en',
                'value'    => 'en_SG',
                'label'    => 'English - en_SG',
                'facebook' => 'en_SG',
            ),
            'en_UK' => array(
                'code'     => 'en',
                'value'    => 'en_UK',
                'label'    => 'English - en_UK',
                'facebook' => 'en_UK',
            ),
            'eo' => array(
                'code'     => 'eo',
                'value'    => 'eo',
                'label'    => 'Esperanto - eo',
                'facebook' => 'eo_EO',
            ),
            'es' => array(
                'code'     => 'es',
                'value'    => 'es',
                'label'    => 'Español - es',
                'facebook' => 'es',
            ),
            'es_AR' => array(
                'code'     => 'es',
                'value'    => 'es_AR',
                'label'    => 'Español - es_AR',
                'facebook' => 'es_LA',
            ),
            'es_CL' => array(
                'code'     => 'es',
                'value'    => 'es_CL',
                'label'    => 'Español - es_CL',
                'facebook' => 'es_CL',
            ),
            'es_CO' => array(
                'code'     => 'es',
                'value'    => 'es_CO',
                'label'    => 'Español - es_CO',
                'facebook' => 'es_CO',
            ),
            'es_CR' => array(
                'code'     => 'es',
                'value'    => 'es_CR',
                'label'    => 'Español - es_CR',
                'facebook' => 'es_LA',
            ),
            'es_DO' => array(
                'code'     => 'es',
                'value'    => 'es_DO',
                'label'    => 'Español - es_DO',
                'facebook' => 'es_LA',
            ),
            'es_EC' => array(
                'code'     => 'es',
                'value'    => 'es_EC',
                'label'    => 'Español - es_EC',
                'facebook' => 'es_LA',
            ),
            'es_ES' => array(
                'code'     => 'es',
                'value'    => 'es_ES',
                'label'    => 'Español - es_ES',
                'facebook' => 'es_ES',
            ),
            'es_GT' => array(
                'code'     => 'es',
                'value'    => 'es_GT',
                'label'    => 'Español - es_GT',
                'facebook' => 'es_LA',
            ),
            'es_MX' => array(
                'code'     => 'es',
                'value'    => 'es_MX',
                'label'    => 'Español - es_MX',
                'facebook' => 'es_MX',
            ),
            'es_PE' => array(
                'code'     => 'es',
                'value'    => 'es_PE',
                'label'    => 'Español - es_PE',
                'facebook' => 'es_LA',
            ),
            'es_PR' => array(
                'code'     => 'es',
                'value'    => 'es_PR',
                'label'    => 'Español - es_PR',
                'facebook' => 'es_LA',
            ),
            'es_UY' => array(
                'code'     => 'es',
                'value'    => 'es_UY',
                'label'    => 'Español - es_UY',
                'facebook' => 'es_LA',
            ),
            'es_VE' => array(
                'code'     => 'es',
                'value'    => 'es_VE',
                'label'    => 'Español - es_VE',
                'facebook' => 'es_VE',
            ),
            'et' => array(
                'code'     => 'et',
                'value'    => 'et',
                'label'    => 'Eesti - et',
                'facebook' => 'et_EE',
            ),
            'eu' => array(
                'code'     => 'eu',
                'value'    => 'eu',
                'label'    => 'Euskara - eu',
                'facebook' => 'eu_ES',
            ),
            'fa_AF' => array(
                'code'     => 'fa',
                'value'    => 'fa_AF',
                'label'    => 'فارسی - fa_AF',
                'facebook' => 'fa_IR',
            ),
            'fa_IR' => array(
                'code'     => 'fa',
                'value'    => 'fa_IR',
                'label'    => 'فارسی - fa_IR',
                'facebook' => 'fa_IR',
            ),
            'fi' => array(
                'code'     => 'fi',
                'value'    => 'fi',
                'label'    => 'Suomi - fi',
                'facebook' => 'fi_FI',
            ),
            'fo' => array(
                'code'     => 'fo',
                'value'    => 'fo',
                'label'    => 'Føroyskt - fo',
                'facebook' => 'fo_FO',
            ),
            'fr' => array(
                'code'     => 'fr',
                'value'    => 'fr',
                'label'    => 'Français - fr',
                'facebook' => 'fr',
            ),
            'fr_BE' => array(
                'code'     => 'fr',
                'value'    => 'fr_BE',
                'label'    => 'Français - fr_BE',
                'facebook' => 'fr_FR',
            ),
            'fr_CA' => array(
                'code'     => 'fr',
                'value'    => 'fr_CA',
                'label'    => 'Français - fr_CA',
                'facebook' => 'fr_CA',
            ),
            'fr_FR' => array(
                'code'     => 'fr',
                'value'    => 'fr_FR',
                'label'    => 'Français - fr_FR',
                'facebook' => 'fr_FR',
            ),
            'fr_CH' => array(
                'code'     => 'fr',
                'value'    => 'fr_CH',
                'label'    => 'Français - fr_CH',
                'facebook' => 'fr_CH',
            ),
            'fur' => array(
                'code'     => 'fur',
                'value'    => 'fur',
                'label'    => 'Furlan - fur',
            ),
            'fy' => array(
                'code'     => 'fy',
                'value'    => 'fy',
                'label'    => 'Frysk - fy',
                'facebook' => 'fy_NL',
            ),
            'gd' => array(
                'code'     => 'gd',
                'value'    => 'gd',
                'label'    => 'Gàidhlig - gd',
            ),
            'gl_ES' => array(
                'code'     => 'gl',
                'value'    => 'gl_ES',
                'label'    => 'Galego - gl_ES',
                'facebook' => 'gl_ES',
            ),
            'gu' => array(
                'code'     => 'gu',
                'value'    => 'gu',
                'label'    => 'ગુજરાતી - gu',
                'facebook' => 'gu_IN',
            ),
            'haz' => array(
                'code'     => 'haz',
                'value'    => 'haz',
                'label'    => 'هزاره گی - haz',
            ),
            'he_IL' => array(
                'code'     => 'he',
                'value'    => 'he_IL',
                'label'    => 'עברית - he_IL',
                'facebook' => 'he_IL',
            ),
            'hi_IN' => array(
                'code'     => 'hi',
                'value'    => 'hi_IN',
                'label'    => 'हिन्दी - hi_IN',
                'facebook' => 'hi_IN',
            ),
            'hr' => array(
                'code'     => 'hr',
                'value'    => 'hr',
                'label'    => 'Hrvatski - hr',
                'facebook' => 'hr_HR',
            ),
            'hu_HU' => array(
                'code'     => 'hu',
                'value'    => 'hu_HU',
                'label'    => 'Magyar - hu_HU',
                'facebook' => 'hu_HU',
            ),
            'hsb' => array(
                'code'     => 'hsb',
                'value'    => 'hsb',
                'label'    => 'Hornjoserbšćina - hsb',
            ),
            'hy' => array(
                'code'     => 'hy',
                'value'    => 'hy',
                'label'    => 'Հայերեն - hy',
                'facebook' => 'hy_AM',
            ),
            'id_ID' => array(
                'code'     => 'id',
                'value'    => 'id_ID',
                'label'    => 'Bahasa Indonesia - id_ID',
                'facebook' => 'id_ID',
            ),
            'is_IS' => array(
                'code'     => 'is',
                'value'    => 'is_IS',
                'label'    => 'Íslenska - is_IS',
                'facebook' => 'is_IS',
            ),
            'it' => array(
                'code'     => 'it',
                'value'    => 'it',
                'label'    => 'Italiano - it',
                'facebook' => 'it',
            ),
            'it_IT' => array(
                'code'     => 'it',
                'value'    => 'it_IT',
                'label'    => 'Italiano - it_IT',
                'facebook' => 'it_IT',
            ),
            'it_CH' => array(
                'code'     => 'it',
                'value'    => 'it_CH',
                'label'    => 'Italiano - it_CH',
                'facebook' => 'it_CH',
            ),
            'iw' => array(
                'code'     => 'iw',
                'value'    => 'iw',
                'label'    => 'iw',
                'facebook' => 'iw',
            ),
            'in' => array(
                'code'     => 'in',
                'value'    => 'in',
                'label'    => 'in',
                'facebook' => 'in',
            ),
            'ja' => array(
                'code'     => 'ja',
                'value'    => 'ja',
                'label'    => '日本語 - ja',
                'facebook' => 'ja_JP',
            ),
            'jv_ID' => array(
                'code'     => 'jv',
                'value'    => 'jv_ID',
                'label'    => 'Basa Jawa - jv_ID',
                'facebook' => 'jv_ID',
            ),
            'ka_GE' => array(
                'code'     => 'ka',
                'value'    => 'ka_GE',
                'label'    => 'ქართული - ka_GE',
                'facebook' => 'ka_GE',
            ),
            'kab' => array(
                'code'     => 'kab',
                'value'    => 'kab',
                'label'    => 'Taqbaylit - kab',
            ),
            'kir' => array(
                'code'     => 'ky',
                'value'    => 'kir',
                'label'    => 'Кыргызча - kir',
            ),
            'kk' => array(
                'code'     => 'kk',
                'value'    => 'kk',
                'label'    => 'Қазақ тілі - kk',
                'facebook' => 'kk_KZ',
            ),
            'km' => array(
                'code'     => 'km',
                'value'    => 'km',
                'label'    => 'ភាសាខ្មែរ - km',
                'facebook' => 'km_KH',
            ),
            'kn' => array(
                'code'     => 'kn',
                'value'    => 'kn',
                'label'    => 'ಕನ್ನಡ - kn',
                'facebook' => 'kn_IN',
            ),
            'ko' => array(
                'code'     => 'ko',
                'value'    => 'ko',
                'label'    => '한국어 - ko',
                'facebook' => 'ko',
            ),
            'ko_KR' => array(
                'code'     => 'ko',
                'value'    => 'ko_KR',
                'label'    => '한국어 - ko_KR',
                'facebook' => 'ko_KR',
            ),
            'lo' => array(
                'code'     => 'lo',
                'value'    => 'lo',
                'label'    => 'ພາສາລາວ - lo',
                'facebook' => 'lo_LA',
            ),
            'lt_LT' => array(
                'code'     => 'lt',
                'value'    => 'lt_LT',
                'label'    => 'Lietuviškai - lt_LT',
                'facebook' => 'lt_LT',
            ),
            'lv' => array(
                'code'     => 'lv',
                'value'    => 'lv',
                'label'    => 'Latviešu valoda - lv',
                'facebook' => 'lv_LV',
            ),
            'mk_MK' => array(
                'code'     => 'mk',
                'value'    => 'mk_MK',
                'label'    => 'македонски јазик - mk_MK',
                'facebook' => 'mk_MK',
            ),
            'ml_IN' => array(
                'code'     => 'ml',
                'value'    => 'ml_IN',
                'label'    => 'മലയാളം - ml_IN',
                'facebook' => 'ml_IN',
            ),
            'mn' => array(
                'code'     => 'mn',
                'value'    => 'mn',
                'label'    => 'Монгол хэл - mn',
                'facebook' => 'mn_MN',
            ),
            'mr' => array(
                'code'     => 'mr',
                'value'    => 'mr',
                'label'    => 'मराठी - mr',
                'facebook' => 'mr_IN',
            ),
            'ms_MY' => array(
                'code'     => 'ms',
                'value'    => 'ms_MY',
                'label'    => 'Bahasa Melayu - ms_MY',
                'facebook' => 'ms_MY',
            ),
            'my_MM' => array(
                'code'     => 'my',
                'value'    => 'my_MM',
                'label'    => 'ဗမာစာ - my_MM',
                'facebook' => 'my_MM',
            ),
            'no' => array(
                'code'     => 'no',
                'value'    => 'no',
                'label'    => 'no',
                'facebook' => 'no',
            ),
            'nb_NO' => array(
                'code'     => 'nb',
                'value'    => 'nb_NO',
                'label'    => 'Norsk Bokmål - nb_NO',
                'facebook' => 'nb_NO',
            ),
            'ne_NP' => array(
                'code'     => 'ne',
                'value'    => 'ne_NP',
                'label'    => 'नेपाली - ne_NP',
                'facebook' => 'ne_NP',
            ),
            'nl' => array(
                'code'     => 'nl',
                'value'    => 'nl',
                'label'    => 'Nl',
                'facebook' => 'nl',
            ),
            'nl_BE' => array(
                'code'     => 'nl',
                'value'    => 'nl_BE',
                'label'    => 'Nederlands - nl_BE',
                'facebook' => 'nl_BE',
            ),
            'nl_NL' => array(
                'code'     => 'nl',
                'value'    => 'nl_NL',
                'label'    => 'Nederlands - nl_NL',
                'facebook' => 'nl_NL',
            ),
            'nl_NL_formal' => array(
                'code'     => 'nl',
                'value'    => 'nl_NL_formal',
                'label'    => 'Nederlands - nl_NL_formal',
                'facebook' => 'nl_NL',
            ),
            'nn_NO' => array(
                'code'     => 'nn',
                'value'    => 'nn_NO',
                'label'    => 'Norsk Nynorsk - nn_NO',
                'facebook' => 'nn_NO',
            ),
            'oci' => array(
                'code'     => 'oc',
                'value'    => 'oci',
                'label'    => 'Occitan - oci',
            ),
            'pa_IN' => array(
                'code'     => 'pa',
                'value'    => 'pa_IN',
                'label'    => 'ਪੰਜਾਬੀ - pa_IN',
                'facebook' => 'pa_IN',
            ),
            'pl_PL' => array(
                'code'     => 'pl',
                'value'    => 'pl_PL',
                'label'    => 'Polski - pl_PL',
                'facebook' => 'pl_PL',
            ),
            'ps' => array(
                'code'     => 'ps',
                'value'    => 'ps',
                'label'    => 'پښتو - ps',
                'facebook' => 'ps_AF',
            ),
            'pt' => array(
                'code'     => 'pt',
                'value'    => 'pt',
                'label'    => 'Português - pt',
                'facebook' => 'pt',
            ),
            'pt_AO' => array(
                'code'     => 'pt',
                'value'    => 'pt_AO',
                'label'    => 'Português - pt_AO',
                'facebook' => 'pt_AO',
            ),
            'pt_BR' => array(
                'code'     => 'pt',
                'value'    => 'pt_BR',
                'label'    => 'Português - pt_BR',
                'facebook' => 'pt_BR',
            ),
            'pt_PT' => array(
                'code'     => 'pt',
                'value'    => 'pt_PT',
                'label'    => 'Português - pt_PT',
                'facebook' => 'pt_PT',
            ),
            'pt_PT_ao90' => array(
                'code'     => 'pt',
                'value'    => 'pt_PT_ao90',
                'label'    => 'Português - pt_PT_ao90',
                'facebook' => 'pt_PT',
            ),
            'rhg' => array(
                'code'     => 'rhg',
                'value'    => 'rhg',
                'label'    => 'Ruáinga - rhg',
            ),
            'ro_RO' => array(
                'code'     => 'ro',
                'value'    => 'ro_RO',
                'label'    => 'Română - ro_RO',
                'facebook' => 'ro_RO',
            ),
            'ru' => array(
                'code'     => 'ru',
                'value'    => 'ru',
                'label'    => 'Русский - ru',
                'facebook' => 'ru',
            ),
            'ru_RU' => array(
                'code'     => 'ru',
                'value'    => 'ru_RU',
                'label'    => 'Русский - ru_RU',
                'facebook' => 'ru_RU',
            ),
            'sah' => array(
                'code'     => 'sah',
                'value'    => 'sah',
                'label'    => 'Сахалыы - sah',
            ),
            'si_LK' => array(
                'code'     => 'si',
                'value'    => 'si_LK',
                'label'    => 'සිංහල - si_LK',
                'facebook' => 'si_LK',
            ),
            'sk_SK' => array(
                'code'     => 'sk',
                'value'    => 'sk_SK',
                'label'    => 'Slovenčina - sk_SK',
                'facebook' => 'sk_SK',
            ),
            'skr' => array(
                'code'     => 'skr',
                'value'    => 'skr',
                'label'    => 'سرائیکی - skr',
            ),
            'sl_SI' => array(
                'code'     => 'sl',
                'value'    => 'sl_SI',
                'label'    => 'Slovenščina - sl_SI',
                'facebook' => 'sl_SI',
            ),
            'snd' => array(
                'code'     => 'sd',
                'value'    => 'snd',
                'label'    => 'سنڌي - snd',
            ),
            'so_SO' => array(
                'code'     => 'so',
                'value'    => 'so_SO',
                'label'    => 'Af-Soomaali - so_SO',
                'facebook' => 'so_SO',
            ),
            'sq' => array(
                'code'     => 'sq',
                'value'    => 'sq',
                'label'    => 'Shqip - sq',
                'facebook' => 'sq_AL',
            ),
            'sr_RS' => array(
                'code'     => 'sr',
                'value'    => 'sr_RS',
                'label'    => 'Српски језик - sr_RS',
                'facebook' => 'sr_RS',
            ),
            'su_ID' => array(
                'code'     => 'su',
                'value'    => 'su_ID',
                'label'    => 'Basa Sunda - su_ID',
                'facebook' => 'su_ID',
            ),
            'sv' => array(
                'code'     => 'sv',
                'value'    => 'sv',
                'label'    => 'Svenska - sv',
                'facebook' => 'sv',
            ),
            'sv_SE' => array(
                'code'     => 'sv',
                'value'    => 'sv_SE',
                'label'    => 'Svenska - sv_SE',
                'facebook' => 'sv_SE',
            ),
            'sw' => array(
                'code'     => 'sw',
                'value'    => 'sw',
                'label'    => 'Kiswahili - sw',
                'facebook' => 'sw_KE',
            ),
            'szl' => array(
                'code'     => 'szl',
                'value'    => 'szl',
                'label'    => 'Ślōnskŏ gŏdka - szl',
                'facebook' => 'sz_PL',
            ),
            'ta_IN' => array(
                'code'     => 'ta',
                'value'    => 'ta_IN',
                'label'    => 'தமிழ் - ta_IN',
                'facebook' => 'ta_IN',
            ),
            'ta_LK' => array(
                'code'     => 'ta',
                'value'    => 'ta_LK',
                'label'    => 'தமிழ் - ta_LK',
                'facebook' => 'ta_IN',
            ),
            'tah' => array(
                'code'     => 'ty',
                'value'    => 'tah',
                'label'    => 'Reo Tahiti - tah',
            ),
            'te' => array(
                'code'     => 'te',
                'value'    => 'te',
                'label'    => 'తెలుగు - te',
                'facebook' => 'te_IN',
            ),
            'th' => array(
                'code'     => 'th',
                'value'    => 'th',
                'label'    => 'ไทย - th',
                'facebook' => 'th_TH',
            ),
            'tl' => array(
                'code'     => 'tl',
                'value'    => 'tl',
                'label'    => 'Tagalog - tl',
                'facebook' => 'tl_PH',
            ),
            'tr' => array(
                'code'     => 'tr',
                'value'    => 'tr',
                'label'    => 'Türkçe - tr',
                'facebook' => 'tr',
            ),
            'tr_TR' => array(
                'code'     => 'tr',
                'value'    => 'tr_TR',
                'label'    => 'Türkçe - tr_TR',
                'facebook' => 'tr_TR',
            ),
            'tt_RU' => array(
                'code'     => 'tt',
                'value'    => 'tt_RU',
                'label'    => 'Татар теле - tt_RU',
                'facebook' => 'tt_RU',
            ),
            'ug_CN' => array(
                'code'     => 'ug',
                'value'    => 'ug_CN',
                'label'    => 'Uyƣurqə - ug_CN',
            ),
            'uk' => array(
                'code'     => 'uk',
                'value'    => 'uk',
                'label'    => 'Українська - uk',
                'facebook' => 'uk_UA',
            ),
            'ur' => array(
                'code'     => 'ur',
                'value'    => 'ur',
                'label'    => 'اردو - ur',
                'facebook' => 'ur_PK',
            ),
            'uz_UZ' => array(
                'code'     => 'uz',
                'value'    => 'uz_UZ',
                'label'    => 'Oʻzbek - uz_UZ',
                'facebook' => 'uz_UZ',
            ),
            'vec' => array(
                'code'     => 'vec',
                'value'    => 'vec',
                'label'    => 'Vèneto - vec',
            ),
            'vi' => array(
                'code'     => 'vi',
                'value'    => 'vi',
                'label'    => 'Tiếng Việt - vi',
                'facebook' => 'vi_VN',
            ),
            'zh' => array(
                'code'     => 'zh',
                'value'    => 'zh',
                'label'    => 'Zh',
                'facebook' => 'zh',
            ),
            'zh_CN' => array(
                'code'     => 'zh',
                'value'    => 'zh_CN',
                'label'    => '中文 (中国) - zh_CN',
                'facebook' => 'zh_CN',
            ),
            'zh_HK' => array(
                'code'     => 'zh',
                'value'    => 'zh_HK',
                'label'    => '中文 (香港) - zh_HK',
                'facebook' => 'zh_HK',
            ),
            'zh_TW' => array(
                'code'     => 'zh',
                'value'    => 'zh_TW',
                'label'    => '中文 (台灣) - zh_TW',
                'facebook' => 'zh_TW',
            ),
        );
    }

      /**
     * Get raw country list
     *
     * @return array
     */
    private function countryList()
    {
        return [
            [
                'value' => 'AF',
                'label' => __('Afghanistan', 'wp-social-reviews')
            ],
            [
                'value' => 'AX',
                'label' => __('Aland Islands', 'wp-social-reviews')
            ],
            ['value' => 'AL', 'label' => __('Albania', 'wp-social-reviews')],
            ['value' => 'DZ', 'label' => __('Algeria', 'wp-social-reviews')],
            [
                'value' => 'AS',
                'label' => __('American Samoa', 'wp-social-reviews')
            ],
            ['value' => 'AD', 'label' => __('Andorra', 'wp-social-reviews')],
            ['value' => 'AO', 'label' => __('Angola', 'wp-social-reviews')],
            ['value' => 'AI', 'label' => __('Anguilla', 'wp-social-reviews')],
            ['value' => 'AQ', 'label' => __('Antarctica', 'wp-social-reviews')],
            [
                'value' => 'AG',
                'label' => __('Antigua and Barbuda', 'wp-social-reviews')
            ],
            ['value' => 'AR', 'label' => __('Argentina', 'wp-social-reviews')],
            ['value' => 'AM', 'label' => __('Armenia', 'wp-social-reviews')],
            ['value' => 'AW', 'label' => __('Aruba', 'wp-social-reviews')],
            ['value' => 'AU', 'label' => __('Australia', 'wp-social-reviews')],
            ['value' => 'AT', 'label' => __('Austria', 'wp-social-reviews')],
            ['value' => 'AZ', 'label' => __('Azerbaijan', 'wp-social-reviews')],
            ['value' => 'BS', 'label' => __('Bahamas', 'wp-social-reviews')],
            ['value' => 'BH', 'label' => __('Bahrain', 'wp-social-reviews')],
            ['value' => 'BD', 'label' => __('Bangladesh', 'wp-social-reviews')],
            ['value' => 'BB', 'label' => __('Barbados', 'wp-social-reviews')],
            ['value' => 'BY', 'label' => __('Belarus', 'wp-social-reviews')],
            ['value' => 'BE', 'label' => __('Belgium', 'wp-social-reviews')],
            ['value' => 'PW', 'label' => __('Belau', 'wp-social-reviews')],
            ['value' => 'BZ', 'label' => __('Belize', 'wp-social-reviews')],
            ['value' => 'BJ', 'label' => __('Benin', 'wp-social-reviews')],
            ['value' => 'BM', 'label' => __('Bermuda', 'wp-social-reviews')],
            ['value' => 'BT', 'label' => __('Bhutan', 'wp-social-reviews')],
            ['value' => 'BO', 'label' => __('Bolivia', 'wp-social-reviews')],
            [
                'value' => 'BQ',
                'label' => __('Bonaire, Saint Eustatius and Saba',
                    'wp-social-reviews')
            ],
            [
                'value' => 'BA',
                'label' => __('Bosnia and Herzegovina', 'wp-social-reviews')
            ],
            ['value' => 'BW', 'label' => __('Botswana', 'wp-social-reviews')],
            [
                'value' => 'BV',
                'label' => __('Bouvet Island', 'wp-social-reviews')
            ],
            ['value' => 'BR', 'label' => __('Brazil', 'wp-social-reviews')],
            [
                'value' => 'IO',
                'label' => __('British Indian Ocean Territory', 'wp-social-reviews')
            ],
            [
                'value' => 'VG',
                'label' => __('British Virgin Islands', 'wp-social-reviews')
            ],
            ['value' => 'BN', 'label' => __('Brunei', 'wp-social-reviews')],
            ['value' => 'BG', 'label' => __('Bulgaria', 'wp-social-reviews')],
            [
                'value' => 'BF',
                'label' => __('Burkina Faso', 'wp-social-reviews')
            ],
            ['value' => 'BI', 'label' => __('Burundi', 'wp-social-reviews')],
            ['value' => 'KH', 'label' => __('Cambodia', 'wp-social-reviews')],
            ['value' => 'CM', 'label' => __('Cameroon', 'wp-social-reviews')],
            ['value' => 'CA', 'label' => __('Canada', 'wp-social-reviews')],
            ['value' => 'CV', 'label' => __('Cape Verde', 'wp-social-reviews')],
            [
                'value' => 'KY',
                'label' => __('Cayman Islands', 'wp-social-reviews')
            ],
            [
                'value' => 'CF',
                'label' => __('Central African Republic', 'wp-social-reviews')
            ],
            ['value' => 'TD', 'label' => __('Chad', 'wp-social-reviews')],
            ['value' => 'CL', 'label' => __('Chile', 'wp-social-reviews')],
            ['value' => 'CN', 'label' => __('China', 'wp-social-reviews')],
            [
                'value' => 'CX',
                'label' => __('Christmas Island', 'wp-social-reviews')
            ],
            [
                'value' => 'CC',
                'label' => __('Cocos (Keeling) Islands', 'wp-social-reviews')
            ],
            ['value' => 'CO', 'label' => __('Colombia', 'wp-social-reviews')],
            ['value' => 'KM', 'label' => __('Comoros', 'wp-social-reviews')],
            [
                'value' => 'CG',
                'label' => __('Republic of the Congo (Brazzaville)', 'wp-social-reviews')
            ],
            [
                'value' => 'CD',
                'label' => __('Democratic Republic of the Congo (Kinshasa)', 'wp-social-reviews')
            ],
            [
                'value' => 'CK',
                'label' => __('Cook Islands', 'wp-social-reviews')
            ],
            ['value' => 'CR', 'label' => __('Costa Rica', 'wp-social-reviews')],
            ['value' => 'HR', 'label' => __('Croatia', 'wp-social-reviews')],
            ['value' => 'CU', 'label' => __('Cuba', 'wp-social-reviews')],
            [
                'value' => 'CW',
                'label' => __('Cura&ccedil;ao', 'wp-social-reviews')
            ],
            ['value' => 'CY', 'label' => __('Cyprus', 'wp-social-reviews')],
            [
                'value' => 'CZ',
                'label' => __('Czech Republic', 'wp-social-reviews')
            ],
            ['value' => 'DK', 'label' => __('Denmark', 'wp-social-reviews')],
            ['value' => 'DJ', 'label' => __('Djibouti', 'wp-social-reviews')],
            ['value' => 'DM', 'label' => __('Dominica', 'wp-social-reviews')],
            [
                'value' => 'DO',
                'label' => __('Dominican Republic', 'wp-social-reviews')
            ],
            ['value' => 'EC', 'label' => __('Ecuador', 'wp-social-reviews')],
            ['value' => 'EG', 'label' => __('Egypt', 'wp-social-reviews')],
            [
                'value' => 'SV',
                'label' => __('El Salvador', 'wp-social-reviews')
            ],
            [
                'value' => 'GQ',
                'label' => __('Equatorial Guinea', 'wp-social-reviews')
            ],
            ['value' => 'ER', 'label' => __('Eritrea', 'wp-social-reviews')],
            ['value' => 'EE', 'label' => __('Estonia', 'wp-social-reviews')],
            ['value' => 'ET', 'label' => __('Ethiopia', 'wp-social-reviews')],
            [
                'value' => 'FK',
                'label' => __('Falkland Islands', 'wp-social-reviews')
            ],
            [
                'value' => 'FO',
                'label' => __('Faroe Islands', 'wp-social-reviews')
            ],
            ['value' => 'FJ', 'label' => __('Fiji', 'wp-social-reviews')],
            ['value' => 'FI', 'label' => __('Finland', 'wp-social-reviews')],
            ['value' => 'FR', 'label' => __('France', 'wp-social-reviews')],
            [
                'value' => 'GF',
                'label' => __('French Guiana', 'wp-social-reviews')
            ],
            [
                'value' => 'PF',
                'label' => __('French Polynesia', 'wp-social-reviews')
            ],
            [
                'value' => 'TF',
                'label' => __('French Southern Territories', 'wp-social-reviews')
            ],
            ['value' => 'GA', 'label' => __('Gabon', 'wp-social-reviews')],
            ['value' => 'GM', 'label' => __('Gambia', 'wp-social-reviews')],
            ['value' => 'GE', 'label' => __('Georgia', 'wp-social-reviews')],
            ['value' => 'DE', 'label' => __('Germany', 'wp-social-reviews')],
            ['value' => 'GH', 'label' => __('Ghana', 'wp-social-reviews')],
            ['value' => 'GI', 'label' => __('Gibraltar', 'wp-social-reviews')],
            ['value' => 'GR', 'label' => __('Greece', 'wp-social-reviews')],
            ['value' => 'GL', 'label' => __('Greenland', 'wp-social-reviews')],
            ['value' => 'GD', 'label' => __('Grenada', 'wp-social-reviews')],
            ['value' => 'GP', 'label' => __('Guadeloupe', 'wp-social-reviews')],
            ['value' => 'GU', 'label' => __('Guam', 'wp-social-reviews')],
            ['value' => 'GT', 'label' => __('Guatemala', 'wp-social-reviews')],
            ['value' => 'GG', 'label' => __('Guernsey', 'wp-social-reviews')],
            ['value' => 'GN', 'label' => __('Guinea', 'wp-social-reviews')],
            [
                'value' => 'GW',
                'label' => __('Guinea-Bissau', 'wp-social-reviews')
            ],
            ['value' => 'GY', 'label' => __('Guyana', 'wp-social-reviews')],
            ['value' => 'HT', 'label' => __('Haiti', 'wp-social-reviews')],
            [
                'value' => 'HM',
                'label' => __('Heard Island and McDonald Islands', 'wp-social-reviews')
            ],
            ['value' => 'HN', 'label' => __('Honduras', 'wp-social-reviews')],
            ['value' => 'HK', 'label' => __('Hong Kong', 'wp-social-reviews')],
            ['value' => 'HU', 'label' => __('Hungary', 'wp-social-reviews')],
            ['value' => 'IS', 'label' => __('Iceland', 'wp-social-reviews')],
            ['value' => 'IN', 'label' => __('India', 'wp-social-reviews')],
            ['value' => 'ID', 'label' => __('Indonesia', 'wp-social-reviews')],
            ['value' => 'IR', 'label' => __('Iran', 'wp-social-reviews')],
            ['value' => 'IQ', 'label' => __('Iraq', 'wp-social-reviews')],
            ['value' => 'IE', 'label' => __('Ireland', 'wp-social-reviews')],
            [
                'value' => 'IM',
                'label' => __('Isle of Man', 'wp-social-reviews')
            ],
            ['value' => 'IL', 'label' => __('Israel', 'wp-social-reviews')],
            ['value' => 'IT', 'label' => __('Italy', 'wp-social-reviews')],
            [
                'value' => 'CI',
                'label' => __('Ivory Coast', 'wp-social-reviews')
            ],
            ['value' => 'JM', 'label' => __('Jamaica', 'wp-social-reviews')],
            ['value' => 'JP', 'label' => __('Japan', 'wp-social-reviews')],
            ['value' => 'JE', 'label' => __('Jersey', 'wp-social-reviews')],
            ['value' => 'JO', 'label' => __('Jordan', 'wp-social-reviews')],
            ['value' => 'KZ', 'label' => __('Kazakhstan', 'wp-social-reviews')],
            ['value' => 'KE', 'label' => __('Kenya', 'wp-social-reviews')],
            ['value' => 'KI', 'label' => __('Kiribati', 'wp-social-reviews')],
            ['value' => 'XK', 'label' => __('Kosovo', 'wp-social-reviews')],
            ['value' => 'KW', 'label' => __('Kuwait', 'wp-social-reviews')],
            ['value' => 'KG', 'label' => __('Kyrgyzstan', 'wp-social-reviews')],
            ['value' => 'LA', 'label' => __('Laos', 'wp-social-reviews')],
            ['value' => 'LV', 'label' => __('Latvia', 'wp-social-reviews')],
            ['value' => 'LB', 'label' => __('Lebanon', 'wp-social-reviews')],
            ['value' => 'LS', 'label' => __('Lesotho', 'wp-social-reviews')],
            ['value' => 'LR', 'label' => __('Liberia', 'wp-social-reviews')],
            ['value' => 'LY', 'label' => __('Libya', 'wp-social-reviews')],
            [
                'value' => 'LI',
                'label' => __('Liechtenstein', 'wp-social-reviews')
            ],
            ['value' => 'LT', 'label' => __('Lithuania', 'wp-social-reviews')],
            ['value' => 'LU', 'label' => __('Luxembourg', 'wp-social-reviews')],
            [
                'value' => 'MO',
                'label' => __('Macao S.A.R., China', 'wp-social-reviews')
            ],
            ['value' => 'MK', 'label' => __('Macedonia', 'wp-social-reviews')],
            ['value' => 'MG', 'label' => __('Madagascar', 'wp-social-reviews')],
            ['value' => 'MW', 'label' => __('Malawi', 'wp-social-reviews')],
            ['value' => 'MY', 'label' => __('Malaysia', 'wp-social-reviews')],
            ['value' => 'MV', 'label' => __('Maldives', 'wp-social-reviews')],
            ['value' => 'ML', 'label' => __('Mali', 'wp-social-reviews')],
            ['value' => 'MT', 'label' => __('Malta', 'wp-social-reviews')],
            [
                'value' => 'MH',
                'label' => __('Marshall Islands', 'wp-social-reviews')
            ],
            ['value' => 'MQ', 'label' => __('Martinique', 'wp-social-reviews')],
            ['value' => 'MR', 'label' => __('Mauritania', 'wp-social-reviews')],
            ['value' => 'MU', 'label' => __('Mauritius', 'wp-social-reviews')],
            ['value' => 'YT', 'label' => __('Mayotte', 'wp-social-reviews')],
            ['value' => 'MX', 'label' => __('Mexico', 'wp-social-reviews')],
            ['value' => 'FM', 'label' => __('Micronesia', 'wp-social-reviews')],
            ['value' => 'MD', 'label' => __('Moldova', 'wp-social-reviews')],
            ['value' => 'MC', 'label' => __('Monaco', 'wp-social-reviews')],
            ['value' => 'MN', 'label' => __('Mongolia', 'wp-social-reviews')],
            ['value' => 'ME', 'label' => __('Montenegro', 'wp-social-reviews')],
            ['value' => 'MS', 'label' => __('Montserrat', 'wp-social-reviews')],
            ['value' => 'MA', 'label' => __('Morocco', 'wp-social-reviews')],
            ['value' => 'MZ', 'label' => __('Mozambique', 'wp-social-reviews')],
            ['value' => 'MM', 'label' => __('Myanmar', 'wp-social-reviews')],
            ['value' => 'NA', 'label' => __('Namibia', 'wp-social-reviews')],
            ['value' => 'NR', 'label' => __('Nauru', 'wp-social-reviews')],
            ['value' => 'NP', 'label' => __('Nepal', 'wp-social-reviews')],
            [
                'value' => 'NL',
                'label' => __('Netherlands', 'wp-social-reviews')
            ],
            [
                'value' => 'NC',
                'label' => __('New Caledonia', 'wp-social-reviews')
            ],
            [
                'value' => 'NZ',
                'label' => __('New Zealand', 'wp-social-reviews')
            ],
            ['value' => 'NI', 'label' => __('Nicaragua', 'wp-social-reviews')],
            ['value' => 'NE', 'label' => __('Niger', 'wp-social-reviews')],
            ['value' => 'NG', 'label' => __('Nigeria', 'wp-social-reviews')],
            ['value' => 'NU', 'label' => __('Niue', 'wp-social-reviews')],
            [
                'value' => 'NF',
                'label' => __('Norfolk Island', 'wp-social-reviews')
            ],
            [
                'value' => 'MP',
                'label' => __('Northern Mariana Islands', 'wp-social-reviews')
            ],
            [
                'value' => 'KP',
                'label' => __('North Korea', 'wp-social-reviews')
            ],
            ['value' => 'NO', 'label' => __('Norway', 'wp-social-reviews')],
            ['value' => 'OM', 'label' => __('Oman', 'wp-social-reviews')],
            ['value' => 'PK', 'label' => __('Pakistan', 'wp-social-reviews')],
            [
                'value' => 'PS',
                'label' => __('Palestinian Territory', 'wp-social-reviews')
            ],
            ['value' => 'PA', 'label' => __('Panama', 'wp-social-reviews')],
            [
                'value' => 'PG',
                'label' => __('Papua New Guinea', 'wp-social-reviews')
            ],
            ['value' => 'PY', 'label' => __('Paraguay', 'wp-social-reviews')],
            ['value' => 'PE', 'label' => __('Peru', 'wp-social-reviews')],
            [
                'value' => 'PH',
                'label' => __('Philippines', 'wp-social-reviews')
            ],
            ['value' => 'PN', 'label' => __('Pitcairn', 'wp-social-reviews')],
            ['value' => 'PL', 'label' => __('Poland', 'wp-social-reviews')],
            ['value' => 'PT', 'label' => __('Portugal', 'wp-social-reviews')],
            [
                'value' => 'PR',
                'label' => __('Puerto Rico', 'wp-social-reviews')
            ],
            ['value' => 'QA', 'label' => __('Qatar', 'wp-social-reviews')],
            ['value' => 'RE', 'label' => __('Reunion', 'wp-social-reviews')],
            ['value' => 'RO', 'label' => __('Romania', 'wp-social-reviews')],
            ['value' => 'RU', 'label' => __('Russia', 'wp-social-reviews')],
            ['value' => 'RW', 'label' => __('Rwanda', 'wp-social-reviews')],
            [
                'value' => 'BL',
                'label' => __('Saint Barth&eacute;lemy', 'wp-social-reviews')
            ],
            [
                'value' => 'SH',
                'label' => __('Saint Helena', 'wp-social-reviews')
            ],
            [
                'value' => 'KN',
                'label' => __('Saint Kitts and Nevis', 'wp-social-reviews')
            ],
            [
                'value' => 'LC',
                'label' => __('Saint Lucia', 'wp-social-reviews')
            ],
            [
                'value' => 'MF',
                'label' => __('Saint Martin (French part)', 'wp-social-reviews')
            ],
            [
                'value' => 'SX',
                'label' => __('Saint Martin (Dutch part)', 'wp-social-reviews')
            ],
            [
                'value' => 'PM',
                'label' => __('Saint Pierre and Miquelon', 'wp-social-reviews')
            ],
            [
                'value' => 'VC',
                'label' => __('Saint Vincent and the Grenadines', 'wp-social-reviews')
            ],
            ['value' => 'SM', 'label' => __('San Marino', 'wp-social-reviews')],
            [
                'value' => 'ST',
                'label' => __('Sao Tome and Principe', 'wp-social-reviews')
            ],
            [
                'value' => 'SA',
                'label' => __('Saudi Arabia', 'wp-social-reviews')
            ],
            ['value' => 'SN', 'label' => __('Senegal', 'wp-social-reviews')],
            ['value' => 'RS', 'label' => __('Serbia', 'wp-social-reviews')],
            ['value' => 'SC', 'label' => __('Seychelles', 'wp-social-reviews')],
            [
                'value' => 'SL',
                'label' => __('Sierra Leone', 'wp-social-reviews')
            ],
            ['value' => 'SG', 'label' => __('Singapore', 'wp-social-reviews')],
            ['value' => 'SK', 'label' => __('Slovakia', 'wp-social-reviews')],
            ['value' => 'SI', 'label' => __('Slovenia', 'wp-social-reviews')],
            [
                'value' => 'SB',
                'label' => __('Solomon Islands', 'wp-social-reviews')
            ],
            ['value' => 'SO', 'label' => __('Somalia', 'wp-social-reviews')],
            [
                'value' => 'ZA',
                'label' => __('South Africa', 'wp-social-reviews')
            ],
            [
                'value' => 'GS',
                'label' => __('South Georgia/Sandwich Islands', 'wp-social-reviews')
            ],
            [
                'value' => 'KR',
                'label' => __('South Korea', 'wp-social-reviews')
            ],
            [
                'value' => 'SS',
                'label' => __('South Sudan', 'wp-social-reviews')
            ],
            ['value' => 'ES', 'label' => __('Spain', 'wp-social-reviews')],
            ['value' => 'LK', 'label' => __('Sri Lanka', 'wp-social-reviews')],
            ['value' => 'SD', 'label' => __('Sudan', 'wp-social-reviews')],
            ['value' => 'SR', 'label' => __('Suriname', 'wp-social-reviews')],
            [
                'value' => 'SJ',
                'label' => __('Svalbard and Jan Mayen', 'wp-social-reviews')
            ],
            ['value' => 'SZ', 'label' => __('Swaziland', 'wp-social-reviews')],
            ['value' => 'SE', 'label' => __('Sweden', 'wp-social-reviews')],
            [
                'value' => 'CH',
                'label' => __('Switzerland', 'wp-social-reviews')
            ],
            ['value' => 'SY', 'label' => __('Syria', 'wp-social-reviews')],
            ['value' => 'TW', 'label' => __('Taiwan', 'wp-social-reviews')],
            ['value' => 'TJ', 'label' => __('Tajikistan', 'wp-social-reviews')],
            ['value' => 'TZ', 'label' => __('Tanzania', 'wp-social-reviews')],
            ['value' => 'TH', 'label' => __('Thailand', 'wp-social-reviews')],
            [
                'value' => 'TL',
                'label' => __('Timor-Leste', 'wp-social-reviews')
            ],
            ['value' => 'TG', 'label' => __('Togo', 'wp-social-reviews')],
            ['value' => 'TK', 'label' => __('Tokelau', 'wp-social-reviews')],
            ['value' => 'TO', 'label' => __('Tonga', 'wp-social-reviews')],
            [
                'value' => 'TT',
                'label' => __('Trinidad and Tobago', 'wp-social-reviews')
            ],
            ['value' => 'TN', 'label' => __('Tunisia', 'wp-social-reviews')],
            ['value' => 'TR', 'label' => __('Turkey', 'wp-social-reviews')],
            [
                'value' => 'TM',
                'label' => __('Turkmenistan', 'wp-social-reviews')
            ],
            [
                'value' => 'TC',
                'label' => __('Turks and Caicos Islands', 'wp-social-reviews')
            ],
            ['value' => 'TV', 'label' => __('Tuvalu', 'wp-social-reviews')],
            ['value' => 'UG', 'label' => __('Uganda', 'wp-social-reviews')],
            ['value' => 'UA', 'label' => __('Ukraine', 'wp-social-reviews')],
            [
                'value' => 'AE',
                'label' => __('United Arab Emirates', 'wp-social-reviews')
            ],
            [
                'value' => 'GB',
                'label' => __('United Kingdom (UK)', 'wp-social-reviews')
            ],
            [
                'value' => 'US',
                'label' => __('United States (US)', 'wp-social-reviews')
            ],
            [
                'value' => 'UM',
                'label' => __('United States (US) Minor Outlying Islands', 'wp-social-reviews')
            ],
            [
                'value' => 'VI',
                'label' => __('United States (US) Virgin Islands', 'wp-social-reviews')
            ],
            ['value' => 'UY', 'label' => __('Uruguay', 'wp-social-reviews')],
            ['value' => 'UZ', 'label' => __('Uzbekistan', 'wp-social-reviews')],
            ['value' => 'VU', 'label' => __('Vanuatu', 'wp-social-reviews')],
            ['value' => 'VA', 'label' => __('Vatican', 'wp-social-reviews')],
            ['value' => 'VE', 'label' => __('Venezuela', 'wp-social-reviews')],
            ['value' => 'VN', 'label' => __('Vietnam', 'wp-social-reviews')],
            [
                'value' => 'WF',
                'label' => __('Wallis and Futuna', 'wp-social-reviews')
            ],
            [
                'value' => 'EH',
                'label' => __('Western Sahara', 'wp-social-reviews')
            ],
            ['value' => 'WS', 'label' => __('Samoa', 'wp-social-reviews')],
            ['value' => 'YE', 'label' => __('Yemen', 'wp-social-reviews')],
            ['value' => 'ZM', 'label' => __('Zambia', 'wp-social-reviews')],
            ['value' => 'ZW', 'label' => __('Zimbabwe', 'wp-social-reviews')],
        ];
    }
}