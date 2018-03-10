<?php

namespace mglaman\DrupalOrgCli\Command\Maintainer;


use Gitter\Client;
use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseNotes extends Command
{

  /**
   * @var \Gitter\Repository
   */
  protected $repository;

  protected $cwd;

  protected function configure()
  {
    $this
      ->setName('maintainer:release-notes')
      ->addArgument('ref1', InputArgument::OPTIONAL, 'Git tag, branch, or SHA')
      ->addArgument('ref2', InputArgument::OPTIONAL, 'Git tag, branch, or SHA', 'HEAD')
      ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output options: json, markdown (md), html. Defaults to HTML.', 'html')
      ->setDescription('Generate release notes.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    parent::initialize($input, $output);
    $this->cwd = getcwd();
    try {
      $client = new Client();
      $this->repository = $client->getRepository($this->cwd);
    }
    catch (\Exception $e) {
      $this->repository = null;
    }
  }

  /**
   * {@inheritdoc}
   *
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if (!$this->repository) {
      $this->stdOut->writeln('You must run this from a Git repository');
      return 1;
    }

    $ref1 = $this->stdIn->getArgument('ref1');
    $ref2 = $this->stdIn->getArgument('ref2');
    if (!$this->stdIn->getArgument('ref1')) {
      // @todo
      $this->stdOut->writeln('Please provide both arguments, for now.');
      return 1;

    } else {
      $tags = $this->repository->getTags();
      if (!in_array($ref1, $tags)) {
        $this->stdOut->writeln(sprintf('The %s tag is not valid.', $ref1));
        return 1;
      }
    }

    $gitLogCommand = sprintf('git log -s --pretty=format:%s %s..%s', '%s', $ref1, $ref2);

    $gitLog = $this->runProcess($gitLogCommand);
    if ($gitLog->getExitCode() != 0) {
      $this->stdOut->writeln('Error getting commit log');
      return 1;
    }

    // @todo sort these by issue type as well.
    $changes = array_filter(explode(PHP_EOL, trim($gitLog->getOutput())));

    switch ($this->stdIn->getOption('format')) {
      case 'json':
        $this->stdOut->writeln(json_encode($changes, JSON_PRETTY_PRINT));
        break;

      case 'markdown':
      case 'md':
      $this->stdOut->writeln(sprintf('Changes since %s', $ref1));
      $this->stdOut->writeln('');
        foreach ($changes as $change) {
          $this->stdOut->writeln(sprintf('* %s', $change));
        }
        break;

      case 'html':
      default:
        $this->stdOut->writeln(sprintf('<p>Changes since %s</p>', $ref1));
        $this->stdOut->writeln('<ul>');
        foreach ($changes as $change) {
          $this->stdOut->writeln(sprintf('<li>%s</li>', $change));
        }
        $this->stdOut->writeln('</ul>');

        break;
    }
  }

}
