<?php

namespace App\Console\Commands;

use App\Entities\Record;
use Illuminate\Console\Command;
use Elasticsearch\Client;
use App\Entities\Fragment;
use App\Entities\Tag;
use Illuminate\Support\Facades\Storage;
use Ixudra\Curl\Facades\Curl;


class ElsIndexFragmentsTags extends Command
{

    private $elasticsearch;
    private $curl;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'els:fragments:tags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index Tags Fragments';

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
        $map = Storage::disk('es_prop')->get('transcription_mapping.json','Content');


        foreach(Tag::all() as $t){

            $i = 0;
            $frgs = $t->fragments()->get();
            $f_count = count($frgs);
            $type=str_slug('tag-'.$t->name);

            $type_map = str_replace('<name_of_type>', $type, $map);
            $response = Curl::to('http://localhost:9200/relacje')
                ->withData( json_decode($type_map,true) )
                ->asJson()
                ->put();

            foreach($frgs as $f){

                $data = $this->refactorToElasticProperties($f);

                $this->elasticsearch->index([
                    'index' => 'relacje',
                    'type' => $type,
                    'id' => $f->id,
                    'body' => $data
                ]);


                $this->info($type.' - fragments: '.$f_count);

                $i++;
                $f_count--;

            }

        }

    }


    private function refactorToElasticProperties($data){

        $array = [''];
        $array['fid'] = $data->id;
        $array['content'] = strip_tags($data->content);
        $array['start'] = $data->start;
        $r = $data->record()->first();
        $array['record_id'] = $r->id;
        $array['record_title'] = $r->title;

        return $array;

    }


}
