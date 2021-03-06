<?php
namespace SEOshop\Service\Contracts;

interface WakatimeServiceInterface
{
    public function projects();
    public function commits($project, $author = null, $page = 1);
    public function daily($date = 'today', $project = null);
}