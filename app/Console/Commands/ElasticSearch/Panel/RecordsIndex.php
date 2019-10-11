<?php

namespace App\Console\Commands\ElasticSearch\Panel;

use App\Entities\MonitorIndx;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Entities\Record;
use Elasticsearch\Client;
use CSD\Image\Image as ImageCSD;
use App\Entities\Fragment;
use App\Entities\Tag;
use Illuminate\Support\Facades\Storage;
use Ixudra\Curl\Facades\Curl;

class RecordsIndex extends Command
{

    private $elasticsearch;
    private $curl;
    private $letters = ['ą','ę','ć','ś','ł','ń','ź','ż'];
    private $letters_to_replace = ['a','e','c','s','l','n','z','z'];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'els:panel:records {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Client $elasticsearch, Curl $curl)
    {
        parent::__construct();
        $this->elasticsearch = $elasticsearch;
        $this->curl = $curl;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $monitor = MonitorIndx::find($this->argument('id'));

        $this->deleteIndex('records');
        $this->deleteIndex('nagrania');

        $monitor = $this->createReordsIndex($monitor);
        $this->createFragmentsIndex($monitor);

        $monitor->end_at = Carbon::now();
        $monitor->status = 1;
        $monitor->save();

    }


    private function createReordsIndex($monitor){

        $map = Storage::disk('es_prop')->get('group_nagrania_fragmenty.json','Content');

        $response = Curl::to('http://localhost:9200/records')
            ->withData( json_decode($map,true) )
            ->asJson()
            ->put();

        $monitor->start_at = Carbon::now();
        $monitor->save();

        foreach(Record::all() as $key=>$record){


            $interviewee = $record->interviewees()->first();

            if(!is_null($interviewee)) {

                $this->elasticsearch->index([
                    'index' => 'records',
                    'type' => 'elements',
                    'id' => $record->id,
                    'body' => $this->prepareBodyIndex($record, $interviewee)
                ]);


            }


        }

        return $monitor;

    }


    private function createFragmentsIndex($monitor){

        $map = Storage::disk('es_prop')->get('nagrania_fragmenty_map.json','Content');

        $response = Curl::to('http://localhost:9200/nagrania')
            ->withData( json_decode($map,true) )
            ->asJson()
            ->put();

        $frgs = Fragment::all();

        $count_frgs = $frgs->count();

        foreach($frgs as $f){


            if($f->record()->first()->interviewees()->count()>0) {

                $this->elasticsearch->index([
                    'index' => 'nagrania',
                    'type' => 'fragmenty',
                    'id' => $f->id,
                    'body' => $this->prepareBodyIndexFragment($f)
                ]);


            }


        }

    }


    private function deleteIndex($name){
        $response = Curl::to('http://localhost:9200/'.$name)->delete();
    }



    private function prepareBodyIndex($r,$interviewee){

        $array = [];

        $ex_title = explode(" ",$r->title);

        if(count($ex_title)>2){
            $last_name = $ex_title[1];
        }else{
            $last_name = $ex_title[1];
        }


        $array['id'] = $r->id;
        $array['record_title'] = $r->title;
        $array['last_name'] = $interviewee->surname;
        $array['last_name_transform'] = $this->makeNameTransform($interviewee->surname).$this->makeNameTransform($interviewee->name);
        $array['record_alias'] = $r->alias;
        $array['type'] = $r->type;
        $array['duration'] = $r->duration;
        $array['record_status'] = $r->status;

        $array['fragments'] = [];
        foreach($r->fragments()->get() as $f){
            array_push($array['fragments'],[
                'fid'=>$f->id,
                'content'=>strip_tags($f->content),
                'start'=>$f->start,
                "intervals"=>$this->getFragmentIntervals($f),
                "tags"=>$this->getFragmentTags($f),
                "places"=>$this->getFragmentPlaces($f)
            ]);
        }

        $array['images'] = $this->getImagesToRecord($interviewee);

        return $array;

    }


    private function getImagesToRecord($interviewee){

        $array = [];

        $images = Storage::disk('photos')->files();

        foreach($images as $key=>$pic){

            $imgobj = ImageCSD::fromFile(storage_path().'/app/photos/' . $pic);

            $author = $imgobj->getAggregate()->getPhotographerName();

            if(!is_null($author)) {

                if ($author == $interviewee->name . ' ' . $interviewee->surname) {

                    $tags = $imgobj->getAggregate()->getKeywords();

                    if(is_array($tags) && !is_null($tags)){
                        $has_tags = true;
                    }else{
                        $has_tags = false;
                    }

                    $size = getimagesize(storage_path().'/app/photos/'.$pic);
                    array_push($array,[
                        'image_name'=>$pic,
                        'image_link'=>'/photos/'.$pic,
                        'image_size'=>$size[0].'x'.$size[1],
                        'tags'=>($has_tags)?implode(' ',$tags):'',
                        'description'=>$imgobj->getAggregate()->getCaption(),
                        'all'=>$this->getAllDataCaption($imgobj)
                    ]);


                }

            }

        }

        return $array;
    }


    private function getAllDataCaption($imgobj){

        $string = $imgobj->getAggregate()->getCaption();

        $tags = $imgobj->getAggregate()->getKeywords();
        if(is_array($tags) && count($tags)>0){
            $string .= ' '.implode(' ',$tags);
        }

        return $string;
    }


    private function prepareBodyIndexFragment($f){

        $array = [];

        $r = $f->record()->first();
        $ex_title = explode(" ",$r->title);

        $interviewee = $r->interviewees()->first();

        $array['fid'] = $f->id;
        $array['record_id'] = $r->id;
        $array['record_title'] = $r->title;
        $array['last_name'] = end($ex_title);
        $array['last_name_transform'] = $this->makeNameTransform($interviewee->surname).$this->makeNameTransform($interviewee->name);
        $array['record_alias'] = $r->alias;
        $array['content'] = strip_tags($f->content);
        $array['start'] = $f->start;
        $array['type'] = Record::find($f->record_id)->type;
        $array['duration'] = Record::find($f->record_id)->duration;

        $array['intervals'] = [];
        foreach($f->intervals()->get() as $inter){
            array_push($array['intervals'],[
                'id'=>$inter->id,
                'begin'=>$inter->begin,
                'end'=>$inter->end
            ]);
        }

        $array['tags'] = [];
        foreach($f->tags()->get() as $tag){
            array_push($array['tags'],[
                'id'=>$tag->id,
                'name'=>$tag->name
            ]);
        }

        $array['places'] = [];
        foreach($f->places()->get() as $tag){
            array_push($array['places'],[
                'id'=>$tag->id,
                'name'=>$tag->name
            ]);
        }

        $array['images'] = [];

        return $array;

    }


    private function getFragmentIntervals($f){

        $array = [];

        foreach($f->intervals()->get() as $inter){
            array_push($array,[
                'id'=>$inter->id,
                'begin'=>$inter->begin,
                'end'=>$inter->end
            ]);
        }

        return $array;

    }


    private function getFragmentTags($f){

        $array = [];

        foreach($f->tags()->get() as $tag){
            array_push($array,[
                'id'=>$tag->id,
                'name'=>$tag->name
            ]);
        }

        return $array;

    }


    private function getFragmentPlaces($f){

        $array = [];

        foreach($f->places()->get() as $tag){
            array_push($array,[
                'id'=>$tag->id,
                'name'=>$tag->name
            ]);
        }

        return $array;

    }

    private function makeNameTransform($last_name){

        $transform = '';

        $arr = $seed = preg_split('//u', mb_strtolower($last_name), -1, PREG_SPLIT_NO_EMPTY);

        foreach($arr as $wl){

            foreach($this->letters as $k=>$l){

                if($wl==$l){
                    $wl = $this->letters_to_replace[$k].'zz';
                }

            }

            $transform .= $wl;

        }

        return $transform;

    }




}
