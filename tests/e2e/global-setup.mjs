import { execFileSync } from 'node:child_process'
import path from 'node:path'

export default function globalSetup() {
    execFileSync('php', ['vendor/bin/testbench', 'workbench:build', '--no-interaction'], {
        cwd: path.resolve(import.meta.dirname, '../..'),
        stdio: 'ignore',
    })
}
