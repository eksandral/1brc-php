# Yet another implementation of 1BRC in PHP
## It's All Gone ThePrimeagen.
He was watching about 1BRC in GO.
A few weeks later, in the internal company's chat, I saw a link to an article on 1BRC in PHP, and people were discussing it.
Then, I thought, am I capable of doing it by myself? Am I good enough in PHP to do it?
The main issue was that PHP does not have built-in multi-thread functionality(`die("It's born to")`).
It can create a new process with `proc_open` but is a blocking operation.
So you can't actually compete with GO, for example.
Until PHP 8.0 where a Fiber class was introduced.

Reed more on my article about it [https://eksandral.github.io/1brc-php](https://eksandral.github.io/1brc-php)

Final result:
```fish
> time php -dmemory_limit=-1 6.php measurements-1000M.txt 10
________________________________________________________
Executed in   31.41 secs    fish           external
   usr time  236.93 secs  160.00 micros  236.93 secs
   sys time   26.93 secs  685.00 micros   26.93 secs
```
