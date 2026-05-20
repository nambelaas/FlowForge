import React, { useState, useEffect } from "react";
import { Head, router } from "@inertiajs/react";
import axios from "axios";
import {
    Play,
    CheckCircle,
    XCircle,
    Loader2,
    RotateCcw,
    Square,
    RefreshCw,
    Layers,
    Activity,
    HeartPulse,
    Zap,
    AlertTriangle,
} from "lucide-react";

export default function Dashboard({
    initialWorkflows,
    initialStepRuns,
    tenantId,
    healthMetrics,
}) {
    const [workflows] = useState(initialWorkflows);
    const [stepRuns, setStepRuns] = useState(initialStepRuns);
    const [metrics, setMetrics] = useState(healthMetrics);
    const [runningWorkflowId, setRunningWorkflowId] = useState(null);
    const [currentRunId, setCurrentRunId] = useState(null);

    useEffect(() => {
        if (window.Echo) {
            window.Echo.channel("workflows-public").listen(
                ".step.updated",
                (e) => {
                    console.log("Sinyal WebSocket Reverb Masuk:", e);

                    if (e.stepRun) {
                        setCurrentRunId(e.stepRun.workflow_run_id);

                        // Perbarui kotak node secara live
                        setStepRuns((prevSteps) =>
                            prevSteps.map((step) =>
                                step.id === e.stepRun.step_id
                                    ? {
                                          ...step,
                                          status: e.stepRun.status,
                                          logs: e.stepRun.logs,
                                          ai_analysis: e.stepRun.ai_analysis,
                                          duration_ms: e.stepRun.duration_ms,
                                      }
                                    : step
                            )
                        );
                    }

                    if (e.latestMetrics) {
                        setMetrics(e.latestMetrics);
                    }

                    if (e.stepRun.status === "RUNNING") {
                        setRunningWorkflowId(
                            e.stepRun.workflow_run?.workflow_id || null
                        );
                    }
                    if (
                        e.stepRun.status === "SUCCESS" ||
                        e.stepRun.status === "FAILED"
                    ) {
                        setRunningWorkflowId(null);
                    }
                }
            );
        }

        return () => {
            if (window.Echo) {
                window.Echo.leaveChannel("workflows-public");
            }
        };
    }, []);

    const handleTriggerWorkflow = async (id) => {
        setRunningWorkflowId(id);

        setStepRuns((prev) =>
            prev.map((s) => ({
                ...s,
                status: "PENDING",
                ai_analysis: null,
                logs: "Waiting in Queue...",
            }))
        );

        try {
            const response = await axios.post(`/workflows/${id}/trigger`);
            console.log(response);
            if (response.data && response.data.workflow_run_id) {
                setCurrentRunId(response.data.workflow_run_id);
            }
        } catch (error) {
            console.error("Gagal memicu alur kerja:", error);
            setRunningWorkflowId(null);
        }
    };

    const handleStopWorkflow = async (id) => {
        console.log("Sending stop signal ", { currentRunId });
        try {
            await axios.post(`/workflows/${id}/stop`);
            setStepRuns((prev) =>
                prev.map((s) =>
                    s.status === "RUNNING" || s.status === "PENDING"
                        ? {
                              ...s,
                              status: "FAILED",
                              logs: "Terminated by User.",
                          }
                        : s
                )
            );
            setRunningWorkflowId(null);
            alert("Alur kerja berhasil dihentikan paksa!");
        } catch (error) {
            console.error("Gagal menghentikan alur kerja:", error);
        }
    };

    return (
        <div className="min-h-screen bg-slate-950 text-slate-50 p-8">
            <Head title="FlowForge Dashboard" />

            {/* Header & Metrics Panel tetap sama (menggunakan data {metrics.*}) */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-slate-800 pb-6 mb-6 gap-4">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight flex items-center gap-2">
                        <Layers className="text-lime-400 w-8 h-8" /> FlowForge{" "}
                        <span className="text-lime-400">Dashboard</span>
                    </h1>
                    <p className="text-slate-400 mt-1">
                        Real-Time Asynchronous Automation Dashboard
                    </p>
                </div>

                <div className="flex items-center gap-2 bg-slate-900 px-4 py-2 rounded-lg border border-slate-800 text-sm">
                    <span className="w-2 h-2 rounded-full bg-lime-500 animate-pulse"></span>
                    <span className="text-slate-300 font-mono">
                        Tenant ID:{" "}
                        {tenantId
                            ? tenantId.length > 8
                                ? `${tenantId.substring(0, 8)}...`
                                : tenantId
                            : "No Active Tenant"}
                    </span>
                </div>
            </div>

            {/* Metrics Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 flex justify-between items-start">
                    <div>
                        <p className="text-xs font-medium text-slate-400 uppercase">
                            Active Runs
                        </p>
                        <h3 className="text-2xl font-bold mt-1 font-mono">
                            {metrics.active_runs}
                        </h3>
                    </div>
                    <div className="p-2 bg-slate-950 rounded-lg text-lime-400">
                        <Activity className="w-4 h-4" />
                    </div>
                </div>
                <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 flex justify-between items-start">
                    <div>
                        <p className="text-xs font-medium text-slate-400 uppercase">
                            Success Rate
                        </p>
                        <h3 className="text-2xl font-bold mt-1 font-mono text-lime-400">
                            {metrics.success_rate}
                        </h3>
                    </div>
                    <div className="p-2 bg-slate-950 rounded-lg text-lime-500">
                        <HeartPulse className="w-4 h-4" />
                    </div>
                </div>
                <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 flex justify-between items-start">
                    <div>
                        <p className="text-xs font-medium text-slate-400 uppercase">
                            Failure Rate
                        </p>
                        <h3 className="text-2xl font-bold mt-1 font-mono text-red-400">
                            {metrics.failure_rate}
                        </h3>
                    </div>
                    <div className="p-2 bg-slate-950 rounded-lg text-red-400">
                        <AlertTriangle className="w-4 h-4" />
                    </div>
                </div>
                <div className="bg-slate-900 p-4 rounded-xl border border-slate-800 flex justify-between items-start">
                    <div>
                        <p className="text-xs font-medium text-slate-400 uppercase">
                            Avg Latency
                        </p>
                        <h3 className="text-2xl font-bold mt-1 font-mono">
                            {metrics.avg_execution_time}
                        </h3>
                    </div>
                    <div className="p-2 bg-slate-950 rounded-lg text-yellow-400">
                        <Zap className="w-4 h-4" />
                    </div>
                </div>
            </div>

            {/* Grid Monitor */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div className="lg:col-span-1 space-y-6">
                    <div className="bg-slate-900 rounded-xl border border-slate-800 p-6">
                        <h2 className="text-xl font-semibold mb-4 text-slate-200">
                            Workflows Controller
                        </h2>
                        {workflows.map((wf) => (
                            <div
                                key={wf.id}
                                className="p-4 bg-slate-950 rounded-lg border border-slate-800 space-y-3"
                            >
                                <div>
                                    <h3 className="font-bold text-slate-100">
                                        {wf.name}
                                    </h3>
                                    <p className="text-xs text-slate-400 mt-1">
                                        {wf.description}
                                    </p>
                                </div>

                                {/* AKSI DINAMIS BUTTONS */}
                                <div className="flex flex-col gap-2">
                                    {runningWorkflowId === wf.id ? (
                                        // Jika sedang berjalan lama, tampilkan BUTTON STOP PACKSA
                                        <button
                                            onClick={() =>
                                                handleStopWorkflow(wf.id)
                                            }
                                            className="w-full flex items-center justify-center gap-2 font-medium py-2.5 px-4 rounded-md bg-red-600 text-white hover:bg-red-500 transition-all text-sm"
                                        >
                                            <Square className="w-4 h-4 fill-current" />{" "}
                                            Stop Process (Timeout Emergency)
                                        </button>
                                    ) : (
                                        // Tombol Trigger Standar / Kirim Ulang
                                        <button
                                            onClick={() =>
                                                handleTriggerWorkflow(wf.id)
                                            }
                                            disabled={
                                                runningWorkflowId !== null
                                            }
                                            className={`w-full flex items-center justify-center gap-2 font-medium py-2.5 px-4 rounded-md transition-all text-sm
                        ${
                            runningWorkflowId !== null
                                ? "bg-slate-800 text-slate-500 cursor-not-allowed"
                                : "bg-lime-400 text-slate-950 hover:bg-lime-300"
                        }`}
                                        >
                                            {stepRuns.some(
                                                (s) =>
                                                    s.status === "FAILED" &&
                                                    (s.workflow_id === wf.id ||
                                                        s.workflow_run
                                                            ?.workflow_id ===
                                                            wf.id)
                                            ) ? (
                                                <>
                                                    <RotateCcw className="w-4 h-4" />{" "}
                                                    Kirim Ulang (Retry
                                                    Execution)
                                                </>
                                            ) : (
                                                <>
                                                    <Play className="w-4 h-4 fill-current" />{" "}
                                                    Trigger Execution
                                                </>
                                            )}
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Monitor Nodes (Kanan) tetap sama */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-slate-900 rounded-xl border border-slate-800 p-6">
                        <h2 className="text-xl font-semibold text-slate-200 flex items-center gap-2 mb-6">
                            <RefreshCw
                                className={`w-4 h-4 text-lime-400 ${
                                    runningWorkflowId ? "animate-spin" : ""
                                }`}
                            />
                            Live Execution Graph (DAG Nodes)
                        </h2>
                        <div className="space-y-4">
                            {stepRuns.map((step) => (
                                <div
                                    key={step.id}
                                    className={`p-5 rounded-lg border bg-slate-950 ${
                                        step.status === "PENDING" &&
                                        "border-slate-800 opacity-60"
                                    } ${
                                        step.status === "RUNNING" &&
                                        "border-yellow-500/50 shadow-md animate-pulse"
                                    } ${
                                        step.status === "SUCCESS" &&
                                        "border-lime-500/40"
                                    } ${
                                        step.status === "FAILED" &&
                                        "border-red-500/40"
                                    }`}
                                >
                                    <div className="flex justify-between items-center mb-3">
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs font-mono font-bold bg-slate-800 text-slate-300 px-2 py-1 rounded border border-slate-700">
                                                {step.type}
                                            </span>
                                            <h4 className="font-bold text-slate-200">
                                                {step.id}
                                            </h4>
                                            {step.duration_ms && (
                                                <span className="text-xs text-slate-500 font-mono">
                                                    ({step.duration_ms}ms)
                                                </span>
                                            )}
                                        </div>
                                        <span
                                            className={`text-xs font-semibold px-2.5 py-0.5 rounded-full inline-flex items-center gap-1.5 border ${
                                                step.status === "PENDING" &&
                                                "bg-slate-950 text-slate-400 border-slate-800"
                                            } ${
                                                step.status === "RUNNING" &&
                                                "bg-yellow-500/10 text-yellow-400 border-yellow-500/20"
                                            } ${
                                                step.status === "SUCCESS" &&
                                                "bg-lime-500/10 text-lime-400 border-lime-500/20"
                                            } ${
                                                step.status === "FAILED" &&
                                                "bg-red-500/10 text-red-400 border-red-500/20"
                                            }`}
                                        >
                                            {step.status === "RUNNING" && (
                                                <Loader2 className="w-3 h-3 animate-spin" />
                                            )}
                                            {step.status}
                                        </span>
                                    </div>
                                    <div className="mt-2 bg-slate-900 p-3 rounded-md border border-slate-800/60 font-mono text-xs text-slate-400 max-h-24 overflow-y-auto">
                                        {step.logs}
                                    </div>
                                    {step.status === "FAILED" &&
                                        step.ai_analysis && (
                                            <div className="mt-3 bg-red-950/20 border border-red-900/40 rounded-md p-4 text-sm text-red-200">
                                                <p className="text-xs italic">
                                                    "{step.ai_analysis}"
                                                </p>
                                            </div>
                                        )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
