<script setup>
import WorkorderTable from '@/components/workorder/WorkorderTable.vue'
</script>

<template>
  <WorkorderTable status="rejected" :show-reject-reason="true" />
</template>
