<?php

use ConsoleKit\Colors;
/**
 * .
 * That command revert a applied list of migration.
 * =========================================================================================
 *
 * 1) Revert all list of new migrations (sort by timestamp name):
 *              Example: php bim down
 *
 * 2) Revert single migration (php bim down [name]):
 *              Example: php bim down 1423573720
 *
 * 3) Revert list of applied migrations for a certain period of time (sort by timestamp name):
 *              Example:  php bim down --from="29.01.2015 00:01" --to="29.01.2015 23:55"
 *
 * 4) Revert list of applied migrations with tag (sort by timestamp name):
 *              Example: php bim down --tag=iws-123
 *
 * Documentation: http://cjp2600.github.io/bim-core/
 *
 * ==========================================================================================
 */
class DownCommand extends BaseCommand
{
    public function execute(array $args, array $options = array())
    {
        $list = $this->getDirectoryTree($this->getMigrationPath(), "php");
        krsort($list); #по убыванию
        if (!empty($list)) {
            foreach ($list as $id => $data) {
                $row  = $data['file'];
                $name = $data['name'];

                # check in db
                $is_new = (!$this->checkInDb($id));
                $class_name = "Migration".$id;

                if ($is_new) {
                    $return_array_new[$id] = array($class_name,"" . $this->getMigrationPath() . $row . "",$name,$data['tags']);
                } else {
                    $return_array_apply[$id] =  array($class_name,"" . $this->getMigrationPath() . $row . "",$name,$data['tags']);
                }
            }

            # filer
            $is_filter = false;
            $f_id = false;
            if ((isset($options['id']))) {
                if (is_string($options['id'])) {
                    $f_id = $options['id'];
                } else {
                    $dialog = new \ConsoleKit\Widgets\Dialog($this->console);
                    $f_id  = $dialog->ask('Type migration id:', $f_id);
                }
            } else if (isset($args[0])){
                if (is_string($args[0])) {
                    $f_id  = $args[0];
                }
            }
            #check tag list
            $filer_tag = (isset($options['tag'])) ? $options['tag'] : false;

            if ($f_id){
                if (isset ($return_array_apply[$f_id])) {
                    $is_filter = true;
                    $return_array_apply = array($f_id => $return_array_apply[$f_id]);
                } else {
                    if (isset ($return_array_apply[$f_id])) {
                        throw new Exception("Migration ".$f_id . " - is already applied");
                    } else {
                        throw new Exception("Migration ".$f_id . " - is not found in applied list");
                    }
                }
            }

            # check to tag list
            if ($filer_tag) {
                $this->padding("down migration for tag : ".$filer_tag);
                $newArrayList = array();
                foreach ($return_array_apply as $id => $mig) {
                    if (!empty($mig[3])) {
                        if (in_array(strtolower($filer_tag), $mig[3])) {
                            $newArrayList[$id] = $mig;
                        }
                    }
                }
                if (!empty($newArrayList)) {
                    $is_filter = true;
                    $return_array_apply = $newArrayList;
                } else {
                    $return_array_apply = array();
                }
            }

            if (!$is_filter) {
                $dialog = new \ConsoleKit\Widgets\Dialog($this->console);
                $type = $dialog->ask('Are you sure you want to remove all applied migration (yes/no)?:', 'yes');
                if (strtolower($type) == "no" || strtolower($type) == "n") {
                    return true;
                }
            }

            if (empty($return_array_apply)){
                $this->info("Applied migrations list is empty.");
                return false;
            }

            $time_start = microtime(true);
            $this->info(" <- Start revert migration:");
            $this->writeln('');
            foreach ( $return_array_apply as $id => $mig) {
                include_once "" . $mig[1] . "";
                if ((method_exists($mig[0], "down"))) {
                    try {
                        if (false !== $mig[0]::down()) {

                            if (Bim\Db\Entity\MigrationsTable::isExistsInTable($id)) {

                                if (Bim\Db\Entity\MigrationsTable::delete($id)) {
                                    $this->writeln($this->color("     - revert   : " . $mig[2], Colors::GREEN));
                                } else {
                                    throw new Exception("Error delete in migration table");
                                }

                            }
                        } else {
                            $this->writeln(Colors::colorize("     - error : " . $mig[2], Colors::RED) . " " . Colors::colorize("(Method Down return false)", Colors::YELLOW));
                        }
                    } catch (Exception $e) {

                        if ((isset($options['debug']))) {
                            $debug = "[" . $e->getFile() . ">" . $e->getLine() . "] ";
                        } else {
                            $debug = "";
                        }

                        $this->writeln(Colors::colorize("     - error : " . $mig[2], Colors::RED) . " " . Colors::colorize("( ".$debug."" . $e->getMessage() . ")", Colors::YELLOW));
                    }
                }
            }

            $time_end = microtime(true);
            $time = $time_end - $time_start;
            $this->writeln('');
            $this->info(" <- ".round($time, 2)."s");
        }
    }
}