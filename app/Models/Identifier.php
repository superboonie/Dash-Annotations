<?php namespace App;

use Illuminate\Database\Eloquent\Model as Eloquent;

function remove_prefix($string, $prefix)
{
    if(substr(strtolower($string), 0, strlen($prefix)) == strtolower($prefix)) 
    {
        return substr($string, strlen($prefix));
    } 
    return $string;
}

function has_prefix($string, $prefix)
{
    return substr(strtolower($string), 0, strlen($prefix)) == strtolower($prefix);
}

function has_suffix($string, $suffix)
{
    return substr(strtolower($string), strlen($string)-strlen($suffix), strlen($suffix)) == strtolower($suffix);
}

class Identifier extends Eloquent {

    public static function IdentifierFromDictionary($dict)
    {
        $identifier = new Identifier;
        $identifier->docset_name = $dict['docset_name'];
        $identifier->docset_filename = $dict['docset_filename'];
        $identifier->docset_platform = $dict['docset_platform'];
        $identifier->docset_bundle = $dict['docset_bundle'];
        $identifier->docset_version = $dict['docset_version'];
        $identifier->page_path = $dict['page_path'];
        $identifier->page_title = (array_key_exists('page_title', $dict)) ? $dict['page_title'] : '';
        $identifier->httrack_source = (array_key_exists('httrack_source', $dict)) ? $dict['httrack_source'] : '';
        $identifier->trim();
        return $identifier;
    }

    public function trim()
    {
        $docset_filename = $this->docset_filename;
        $docset_filename = preg_replace('/\\.docset$/', '', $docset_filename);
        $docset_filename = preg_replace('/[0-9]+\.*[0-9]+(\.*[0-9]+)*/', '', $docset_filename); // remove versions
        $docset_filename = trim(str_replace(range(0,9),'',$docset_filename)); // remove all numbers
        $this->docset_filename = $docset_filename;
        $this->trim_apple_docset_names();
    
        if($this->docset_filename == "Apple_API_Reference")
        {
            $this->httrack_source = str_replace("?language=objc", "", $this->httrack_source);
            $this->httrack_source = str_replace("/ns", "/", $this->httrack_source);
            $this->httrack_source = str_replace("https://", "", $this->httrack_source);
        }
        $page_path = $this->page_path;
        $page_path = str_replace("https://", "http://", $page_path);
        $page_path = str_replace("swiftdoc.org/swift-2/", "swiftdoc.org/", $page_path);
        $basename = basename($page_path);
        $page_path = substr($page_path, 0, strlen($page_path)-strlen($basename));
        $basename = str_replace(['-2.html', '-3.html', '-4.html', '-5.html', '-6.html', '-7.html', '-8.html', '-9.html'], '.html', $basename);
        $page_path = preg_replace('/v[0-9]+\.*[0-9]+(\.*[0-9]+)*/', '', $page_path); // remove versions like /v1.1.0/
        $page_path = preg_replace('/[0-9]+\.*[0-9]+(\.*[0-9]+)*/', '', $page_path); // remove versions like /1.1.0/
        $page_path = preg_replace('/[0-9]+_*[0-9]+(_*[0-9]+)*/', '', $page_path); // remove versions that use _ instead of . (SQLAlchemy)
        $page_path = str_replace(range(0,9), '', $page_path); // remove all numbers
        $page_path = str_replace(['/-alpha/', '/-alpha./', '/-alpha-/', '/-beta/', '/-beta./', '/-beta-/', '/-rc/', '/-rc./', '/-rc-/', '/.alpha/', '/.alpha./', '/.alpha-/', '/.beta/', '/.beta./', '/.beta-/', '/.rc/', '/.rc./', '/.rc-/'], '/', $page_path);
        $page_path = remove_prefix($page_path, "www."); // remove "www." for online-cloned docsets
        $page_path = trim(str_replace('//', '/', $page_path));
        $page_path .= $basename;
        $this->page_path = $page_path;
    }

    public function trim_apple_docset_names()
    {
        if($this->docset_filename == 'prerelease')
        {
            if(has_prefix($this->page_path, 'ios/'))
            {
                $this->docset_filename = "com.apple.adc.documentation.iOS";
                $this->page_path = substr($this->page_path, strlen('ios/'));
            }
            else if(has_prefix($this->page_path, 'mac/'))
            {
                $this->docset_filename = "com.apple.adc.documentation.OSX";
                $this->page_path = substr($this->page_path, strlen('mac/'));
            }
        }
        else if($this->docset_filename == "ios")
        {
            $this->docset_filename = "com.apple.adc.documentation.iOS";
        }
        else if($this->docset_filename == "mac")
        {
            $this->docset_filename = "com.apple.adc.documentation.OSX";
        }
        else if(has_suffix($this->docset_filename, "AppleOSX.CoreReference"))
        {
            $this->docset_filename = "com.apple.adc.documentation.OSX";
        }
        else if(has_suffix($this->docset_filename, "AppleiOS.iOSLibrary"))
        {
            $this->docset_filename = "com.apple.adc.documentation.iOS";
        }
    }

    public function find_in_db()
    {
        $toMatch = ['docset_filename' => $this->docset_filename,
                    'page_path' => $this->page_path,
                   ];

        if($this->docset_filename == "Mono" && !empty($this->httrack_source))
        {
            $toMatch = ['docset_filename' => $this->docset_filename,
                        'httrack_source' => $this->httrack_source,
                       ];
        }
        if($this->docset_filename == "Apple_API_Reference" && !empty($this->httrack_source))
        {
            $toMatch = ['httrack_source' => $this->httrack_source,
                       ];
        }
        return Identifier::where($toMatch)->first();
    }

    public function entries()
    {
        return $this->hasMany('App\Entry');
    }
}
