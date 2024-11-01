<?php

declare(strict_types=1);

  use Workbench\App\Models\Post;
  use Workbench\App\Models\Tag;
  use Workbench\App\Models\Video;

  beforeEach(function () {
    Post::truncate();
    Video::truncate();
    Tag::truncate();
  });

it('Morph To Many Elastic To Elastic Model', function () {

  $video = new Video(['name' => 'foo']);
  $video->saveWithoutRefresh();

  $video2 = new Video(['name' => 'bar']);
  $video2->saveWithoutRefresh();

  $tag = new Tag(['name' => 'baz']);
  $tag->saveWithoutRefresh();

  $tag2 = new Tag(['name' => 'qux']);
  $tag2->saveWithoutRefresh();

  // MorphToMany (pivot is empty)
  $video->tags()->sync([$tag->id, $tag2->id]);
  $check = Video::query()->find($video->id);
  $this->assertEquals(2, $check->tags->count());

  // MorphToMany (pivot is not empty)
  $video->tags()->sync($tag);
  $check = Video::query()->find($video->id);

  #wait for ES to catch up
  sleep(1);
  $this->assertEquals(1, $check->tags->count());

  // Attach MorphToMany
  $video->tags()->sync([]);
  sleep(1);
  $check = Video::query()->find($video->id);
  $this->assertEquals(0, $check->tags->count());
  $video->tags()->attach($tag);
  $video->tags()->attach($tag); // ignore duplicates
  sleep(1);
  $check = Video::query()->find($video->id);
  $this->assertEquals(1, $check->tags->count());

  // Inverse MorphToMany (pivot is empty)
  $tag->videos()->sync([$video->id, $video2->id]);
  $check = Tag::query()->find($tag->id);
  $this->assertEquals(2, $check->videos->count());

});
