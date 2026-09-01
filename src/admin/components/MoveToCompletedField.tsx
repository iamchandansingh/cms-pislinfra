import React, { useState } from "react";

const MoveToCompletedField = (props: any) => {
  const { name, value, onChange } = props;
  const [loading, setLoading] = useState(false);
  const [msg, setMsg] = useState("");

  const handleMove = async () => {
    const isConfirmed = window.confirm("Are you sure you want to Move this project to Completed Projects?\n\nThis will:\n1. Save it in Completed Projects with all images & details\n2. Update Nationwide Coverage map status to Completed\n3. Remove it from Ongoing Projects");
    if (!isConfirmed) return;

    setLoading(true);
    setMsg("");
    try {
      // Find project name or ID from inputs
      const nameInput = document.querySelector('input[name="name"]') as HTMLInputElement;
      const projectName = nameInput ? nameInput.value : "";

      const res = await fetch("/api/ongoing-projects/move-to-completed", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: projectName }),
      });

      const data = await res.json();
      if (res.ok && data.success) {
        setMsg("🎉 Successfully moved to Completed Projects!");
        alert(`Success! "${projectName || "Project"}" has been moved to Completed Projects.`);
        // Navigate to Completed Projects or Ongoing list
        window.location.href = "/admin/content-manager/collection-types/api::completed-project.completed-project";
      } else {
        alert(data.error?.message || "Failed to move project. Please try again.");
      }
    } catch (err: any) {
      console.error(err);
      alert("Error moving project: " + (err.message || "Unknown error"));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      marginTop: "12px",
      padding: "16px",
      backgroundColor: "#f0fdf4",
      border: "1px solid #bbf7d0",
      borderRadius: "8px",
      display: "flex",
      flexDirection: "column",
      gap: "10px"
    }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", flexWrap: "wrap", gap: "10px" }}>
        <div>
          <strong style={{ fontSize: "14px", color: "#166534", display: "block" }}>
            Project Status Action
          </strong>
          <span style={{ fontSize: "12px", color: "#15803d" }}>
            Current Status: <strong>{value || "Ongoing"}</strong>
          </span>
        </div>

        <button
          type="button"
          disabled={loading}
          onClick={handleMove}
          style={{
            padding: "10px 18px",
            backgroundColor: "#10b981",
            color: "#ffffff",
            border: "none",
            borderRadius: "6px",
            fontSize: "13px",
            fontWeight: "700",
            cursor: loading ? "not-allowed" : "pointer",
            display: "inline-flex",
            alignItems: "center",
            gap: "8px",
            boxShadow: "0 2px 6px rgba(16, 185, 129, 0.3)",
            transition: "all 0.2s ease"
          }}
        >
          {loading ? "Moving to Completed..." : "✓ Move to Completed Project"}
        </button>
      </div>

      {msg && (
        <div style={{ fontSize: "13px", color: "#166534", fontWeight: "600" }}>
          {msg}
        </div>
      )}
    </div>
  );
};

export default MoveToCompletedField;
