<?php

namespace React\Tests\Http\Parser;

use React\Http\FileInterface;
use React\Http\Parser\Multipart;
use React\Http\Request;
use React\Stream\ThroughStream;
use React\Tests\Http\TestCase;

class MultipartParserTest extends TestCase
{

    public function testPostKey()
    {
        $files = [];
        $post = [];

        $boundary = "---------------------------5844729766471062541057622570";

        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'Content-Type' => 'multipart/mixed; boundary=' . $boundary,
        ]);
        $request->on('post', function ($key, $value) use (&$post) {
            $post[$key] = $value;
        });
        $request->on('file', function (FileInterface $file) use (&$files) {
            $files[] = $file;
        });

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $data .= "--$boundary--\r\n";

        new Multipart($request);
        $request->emit('data', [$data]);

        $this->assertEmpty($files);
        $this->assertEquals(
            [
                'users[one]' => 'single',
                'users[two]' => 'second',
            ],
            $post
        );
    }

    public function testFileUpload()
    {
        $files = [];
        $post = [];

        $boundary = "---------------------------12758086162038677464950549563";

        $request = new Request('POST', 'http://example.com/', [], 1.1, [
            'Content-Type' => 'multipart/form-data',
        ]);
        $request->on('post', function ($key, $value) use (&$post) {
            $post[] = [$key => $value];
        });
        $request->on('file', function (FileInterface $file) use (&$files) {
            $files[] = $file;
        });

        $file = base64_decode("R0lGODlhAQABAIAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==");

        new Multipart($request);

        $data  = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[one]\"\r\n";
        $data .= "\r\n";
        $data .= "single\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"users[two]\"\r\n";
        $data .= "\r\n";
        $data .= "second\r\n";
        $request->emit('data', [$data]);
        $request->emit('data', ["--$boundary\r\n"]);
        $request->emit('data', ["Content-disposition: form-data; name=\"user\"\r\n"]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', ["single\r\n"]);
        $request->emit('data', ["--$boundary\r\n"]);
        $request->emit('data', ["content-Disposition: form-data; name=\"user2\"\r\n"]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', ["second\r\n"]);
        $request->emit('data', ["--$boundary\r\n"]);
        $request->emit('data', ["Content-Disposition: form-data; name=\"users[]\"\r\n"]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', ["first in array\r\n"]);
        $request->emit('data', ["--$boundary\r\n"]);
        $request->emit('data', ["Content-Disposition: form-data; name=\"users[]\"\r\n"]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', ["second in array\r\n"]);
        $request->emit('data', ["--$boundary\r\n"]);
        $request->emit('data', ["Content-Disposition: form-data; name=\"file\"; filename=\"User.php\"\r\n"]);
        $request->emit('data', ["Content-type: text/php\r\n"]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', ["<?php echo 'User';\r\n"]);
        $request->emit('data', ["\r\n"]);
        $line = "--$boundary";
        $lines = str_split($line, round(strlen($line) / 2));
        $request->emit('data', [$lines[0]]);
        $request->emit('data', [$lines[1]]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', ["Content-Disposition: form-data; name=\"files[]\"; filename=\"blank.gif\"\r\n"]);
        $request->emit('data', ["content-Type: image/gif\r\n"]);
        $request->emit('data', ["X-Foo-Bar: base64\r\n"]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', [$file . "\r\n"]);
        $request->emit('data', ["--$boundary\r\n"]);
        $request->emit('data', ["Content-Disposition: form-data; name=\"files[]\"; filename=\"User.php\"\r\n" .
                       "Content-Type: text/php\r\n" .
                       "\r\n" .
                       "<?php echo 'User';\r\n"]);
        $request->emit('data', ["\r\n"]);
        $request->emit('data', ["--$boundary--\r\n"]);

        $this->assertEquals(6, count($post));
        $this->assertEquals(
            [
                ['users[one]' => 'single'],
                ['users[two]' => 'second'],
                ['user' => 'single'],
                ['user2' => 'second'],
                ['users[]' => 'first in array'],
                ['users[]' => 'second in array'],
            ],
            $post
        );

        $this->assertEquals(3, count($files));
        $this->assertEquals('file', $files[0]->getName());
        $this->assertEquals('User.php', $files[0]->getFilename());
        $this->assertEquals('text/php', $files[0]->getType());

        $this->assertEquals('files[]', $files[1]->getName());
        $this->assertEquals('blank.gif', $files[1]->getFilename());
        $this->assertEquals('image/gif', $files[1]->getType());

        $this->assertEquals('files[]', $files[2]->getName());
        $this->assertEquals('User.php', $files[2]->getFilename());
        $this->assertEquals('text/php', $files[2]->getType());
    }
}
