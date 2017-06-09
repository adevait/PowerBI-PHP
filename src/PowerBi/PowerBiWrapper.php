<?php

namespace PowerBi;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use PowerBi\Exception\ConfigException;

/**
 * Class PowerBiWrapper.
 */
class PowerBiWrapper
{
    /**
     * Power BI cli server path
     * 
     * @var string
     */
    protected $path = 'powerbi';

    /**
     * Params
     * 
     * @var array
     */
    protected $params = [];

    /**
     * Power BI cli server path
     * 
     * @var string
     */
    protected $command = '';

    /**
     * Init.
     * 
     * @param array $params config params
     */
    public function __construct($params = [])
    {
        $this->params = $params;

        if (empty($params)) {
            throw new ConfigException('Nothing to set! Your config params are empty!');
        }

        $command = $this->formatInput($params);

        $this->command = $command;

        $this->execute('config '.$command);
    }

    /**
     * Get Power BI version
     * 
     * @return string
     */
    public function version()
    {
        $this->execute('-V');

        return $this->parseResponse($this->execute('-V'));
    }

    /**
     * Returns a list of all configured values
     * 
     * @return string
     */
    public function config()
    {
        return $this->execute('config');
    }

    /**
     * Returns a list of all workspaces within a workspace collection
     * 
     * @return array
     */
    public function workspaces()
    {
        return $this->parseResponse($this->execute('get-workspaces'), __FUNCTION__);
    }

    /**
     * Creates a new workspaced within a workspace collection
     * 
     * @return array
     */
    public function createWorkspace()
    {
        return $this->parseResponse($this->execute('create-workspace'), __FUNCTION__);
    }

    /**
     * Returns a list of all datasets within a workspace
     * 
     * @return array
     */
    public function datasets()
    {
        return $this->parseResponse($this->execute('get-datasets '.$this->command), __FUNCTION__);
    }

    /**
     * Deletes a dataset and any underlying linked reports
     * 
     * @return string
     */
    public function deleteDataset($datasetId)
    {
        return $this->execute('delete-dataset '.$this->command.' -d '.$datasetId);
    }

    /**
     * Returns a list of all reports within a workspace
     * 
     * @return array
     */
    public function reports()
    {
        return $this->parseResponse($this->execute('get-reports '.$this->command), __FUNCTION__);
    }

    /**
     * Imports a PBIX file
     * 
     * @todo 
     * - Implement overwrite
     * - Implement assing to different users & roles
     * 
     * @param  array  $params    [description]
     * @param  str  $filepath  [description]
     * @param  str  $name      [description]
     * @param  bool $overwrite [description]
     * 
     * @return [type]             [description]
     */
    public function import($params, $filepath, $name, $overwrite = false)
    {
        $command = $this->formatInput($params);

        if ($overwrite) {
            $command.=' -o true';
        }
        
        return $this->parseResponse($this->execute('import -f '.$filepath.' -n '.$name.' '.$command), __FUNCTION__);
    }

    /**
     * Create Token
     * 
     * @param  [type] $params [description]
     * 
     * @return [type]         [description]
     */
    public function createToken($params)
    {
        $command = $this->formatInput($params);

        return $this->parseResponse($this->execute('create-embed-token '.$command), __FUNCTION__);
    }

    /**
     * Execute command
     * 
     * @param  str $command Power BI command
     * 
     * @return string
     */
    public function execute($command)
    {
        $process = new Process($this->path.' '.$command, storage_path('powerbi'));
        $process->setTimeout(6400);
        $process->enableOutput();
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Format and sanitize input
     * 
     * @param  array $input
     * 
     * @return string
     */
    public function formatInput($input)
    {
        return implode(
            ' ',
            array_map(
                function ($v, $k) {
                    return sprintf("%s %s", $k, $v);
                },
                $input,
                array_keys($input)
            )
        );
    }

    /**
     * Parse Power BI response
     * 
     * @todo  
     * - Fix the RegExp for workspaces. Doesn't return the first workspace
     * 
     * @param  [type] $response [description]
     * 
     * @return [type]           [description]
     */
    public function parseResponse($response, $output = false)
    {
        if (!$output) {
            return trim($response);
        }

        switch ($output) {
            case 'reports':
            case 'datasets':
                $m = [];

                preg_match_all("/\[ powerbi \] ID: (.*) \|/", $response, $matchesId);
                preg_match_all("/Name: (.*)/", $response, $matchesName);

                if (!isset($matchesId[1]) || !isset($matchesName[1]) || !is_array($matchesName[1])) {
                    return $m;
                }

                foreach ($matchesId[1] as $key => $value) {
                    $m[] = ['id'=>$value, 'name' => $matchesName[1][$key]];
                }
                
                return $m;
                break;
            case 'import':
                preg_match("/\[ powerbi \] Import ID: (.*)/", $response, $matchesId);

                if (!isset($matchesId[1])) {
                    return [];
                }

                return [['id' => $matchesId[1]]];
                break;
            case 'createWorkspace':
                preg_match_all("/\[ powerbi \] Workspace created:(.*)/", $response, $matchesId);
                return isset($matchesId[1]) ? $matchesId[1] : null;
                break;
            case 'workspaces':
                // TODO: Fix the RegExp! Doesn't return the first workspace
                $response = preg_replace('/\[ powerbi \] =+\n(.*)/', '', $response);
                preg_match_all("/\[ powerbi \] (.*)/", $response, $matchesId);
                return isset($matchesId[1]) ? $matchesId[1] : null;
                break;
            case 'createToken':
                preg_match_all("/\[ powerbi \] Embed Token: (.*)/", $response, $matchesId);
                $token = isset($matchesId[1]) ? $matchesId[1] : '';
                return trim(reset($token));
            default:
                # code...
                break;
        }

        return trim($response);
    }
}
